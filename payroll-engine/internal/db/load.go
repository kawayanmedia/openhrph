// Package db loads payroll inputs from MySQL and writes the results back.
//
// Every query filters by company_id explicitly: the engine does not rely on the
// application's global scopes, so a misconfigured run can never touch another
// company's payroll.
package db

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"sort"
	"strconv"
	"strings"
	"time"

	_ "github.com/go-sql-driver/mysql"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/statutory"
)

// ErrNoPayroll is returned when the payroll id does not exist.
var ErrNoPayroll = errors.New("payroll not found")

// ErrStatutoryCoverage is returned when the payroll date falls outside the
// effective window of a statutory table.
var ErrStatutoryCoverage = errors.New("statutory table does not cover the payroll date")

// Open connects to MySQL using a DSN, or DB_* environment variables.
func Open(dsn string) (*sql.DB, error) {
	if dsn == "" {
		dsn = DSNFromEnv()
	}

	if dsn == "" {
		return nil, errors.New("no database connection configured (pass --dsn or set DB_*)")
	}

	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, err
	}

	db.SetMaxOpenConns(8)
	db.SetMaxIdleConns(4)
	db.SetConnMaxLifetime(5 * time.Minute)

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	if err := db.PingContext(ctx); err != nil {
		return nil, fmt.Errorf("database ping: %w", err)
	}

	return db, nil
}

// DSNFromEnv builds a go-sql-driver DSN from the Laravel DB_* variables.
func DSNFromEnv() string {
	database := os.Getenv("DB_DATABASE")
	if database == "" {
		return ""
	}

	host := envOr("DB_HOST", "127.0.0.1")
	port := envOr("DB_PORT", "3306")
	user := envOr("DB_USERNAME", "root")
	password := os.Getenv("DB_PASSWORD")

	return fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true&charset=utf8mb4&collation=utf8mb4_unicode_ci&loc=UTC",
		user, password, host, port, database)
}

func envOr(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}

	return fallback
}

// Run describes the payroll being processed.
type Run struct {
	PayrollID   int64
	CompanyID   int64
	GroupID     int64
	Status      string
	Type        string
	Cutoff      string
	DateFrom    time.Time
	DateTo      time.Time
	PayrollDate time.Time
	Meta        map[string]string
}

// Input is everything the computation needs.
type Input struct {
	Run       Run
	Config    model.Config
	Employees []model.Employee
}

// Lock acquires a MySQL advisory lock for the payroll run.
func Lock(ctx context.Context, db *sql.DB, payrollID int64) (func(), error) {
	name := fmt.Sprintf("hris_payroll_%d", payrollID)

	var acquired sql.NullInt64
	if err := db.QueryRowContext(ctx, "SELECT GET_LOCK(?, 30)", name).Scan(&acquired); err != nil {
		return nil, fmt.Errorf("acquire lock: %w", err)
	}

	if !acquired.Valid || acquired.Int64 != 1 {
		return nil, fmt.Errorf("payroll %d is already being processed", payrollID)
	}

	return func() {
		_, _ = db.ExecContext(context.Background(), "SELECT RELEASE_LOCK(?)", name)
	}, nil
}

// Load reads the payroll, its group, employees, attendance and statutory tables.
func Load(ctx context.Context, db *sql.DB, payrollID int64) (Input, error) {
	var input Input

	row := db.QueryRowContext(ctx, `
		SELECT p.id, p.company_id, p.payroll_group_id, COALESCE(p.status, 'open'),
		       COALESCE(p.cut_off, ''), p.date_from, p.date_to, p.payroll_date,
		       COALESCE(p.type, 'regular'), p.meta
		FROM payrolls p
		WHERE p.id = ? AND p.deleted_at IS NULL`, payrollID)

	var (
		meta        sql.NullString
		rawCutoff   string
		payrollType string
	)

	if err := row.Scan(&input.Run.PayrollID, &input.Run.CompanyID, &input.Run.GroupID,
		&input.Run.Status, &rawCutoff, &input.Run.DateFrom, &input.Run.DateTo,
		&input.Run.PayrollDate, &payrollType, &meta); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return input, ErrNoPayroll
		}

		return input, fmt.Errorf("load payroll: %w", err)
	}

	input.Run.Type = strings.ToLower(strings.TrimSpace(payrollType))
	if input.Run.Type == "" {
		input.Run.Type = model.RunRegular
	}

	input.Run.Meta = map[string]string{}
	if meta.Valid && meta.String != "" {
		_ = json.Unmarshal([]byte(meta.String), &input.Run.Meta)
	}

	// A payroll without a cut-off used to skip every first/last-pay schedule
	// silently. Derive the cut-off from the payroll date instead.
	input.Run.Cutoff = normaliseCutoff(rawCutoff, input.Run.PayrollDate)

	cfg, err := loadConfig(ctx, db, input.Run)
	if err != nil {
		return input, err
	}

	input.Config = cfg

	employees, err := loadEmployees(ctx, db, input.Run, cfg)
	if err != nil {
		return input, err
	}

	input.Employees = filterEmployees(input.Run, employees)

	return input, nil
}

// filterEmployees narrows a run to the employees it is allowed to pay.
// Final-pay runs only settle separated employees, and any run may restrict
// itself to an explicit employee_ids list in its meta.
func filterEmployees(run Run, employees []model.Employee) []model.Employee {
	allowed := map[int64]bool{}
	if raw := strings.TrimSpace(run.Meta["employee_ids"]); raw != "" {
		for _, part := range strings.Split(raw, ",") {
			if id, err := strconv.ParseInt(strings.TrimSpace(part), 10, 64); err == nil {
				allowed[id] = true
			}
		}
	}

	filtered := make([]model.Employee, 0, len(employees))

	for _, employee := range employees {
		if len(allowed) > 0 && !allowed[employee.ID] {
			continue
		}

		if run.Type == model.RunFinalPay && employee.SeparatedOn == "" {
			continue
		}

		filtered = append(filtered, employee)
	}

	return filtered
}

func normaliseCutoff(cutoff string, payrollDate time.Time) string {
	normalised := statutory.NormalizeSchedule(cutoff)

	if normalised != statutory.ScheduleEveryPay {
		return string(normalised)
	}

	if strings.TrimSpace(cutoff) != "" && strings.TrimSpace(cutoff) != "0" {
		return string(normalised)
	}

	if payrollDate.Day() <= 15 {
		return string(statutory.ScheduleFirstPay)
	}

	return string(statutory.ScheduleLastPay)
}

func loadConfig(ctx context.Context, db *sql.DB, run Run) (model.Config, error) {
	cfg := model.Config{
		Frequency:                  statutory.FrequencySemiMonthly,
		Cutoff:                     run.Cutoff,
		RunType:                    run.Type,
		Meta:                       run.Meta,
		DaysPerYear:                261,
		HoursPerDay:                8,
		SSSBrackets:                statutory.SSS2025Schedule(),
		PhilHealth:                 statutory.DefaultPhilHealthConfig(),
		PagIBIG:                    statutory.DefaultPagIBIGConfig(),
		GSIS:                       statutory.DefaultGSISConfig(),
		TaxBrackets:                statutory.TRAINTaxBrackets(statutory.FrequencySemiMonthly),
		AnnualTaxBrackets:          statutory.TRAINTaxBrackets(statutory.FrequencyMonthly),
		PayWithoutTimesheet:        true,
		PayUnworkedRegularHolidays: true,
		SssBase:                    "basic",
		PhilHealthBase:             "basic",
		PagIBIGBase:                "basic",
		GSISBase:                   "basic",
		PayrollDate:                run.PayrollDate,
	}

	var (
		frequency   string
		daysYear    sql.NullInt64
		hoursDay    sql.NullInt64
		sssSchedule sql.NullString
		phSchedule  sql.NullString
		piSchedule  sql.NullString
		taxSchedule sql.NullString
	)

	err := db.QueryRowContext(ctx, `
		SELECT COALESCE(frequency, 'semi-monthly'), days_year, hours_day,
		       sss_schedule, phil_health_schedule, hdmf_schedule, tax_schedule
		FROM payroll_groups
		WHERE id = ? AND company_id = ? AND deleted_at IS NULL`,
		run.GroupID, run.CompanyID).
		Scan(&frequency, &daysYear, &hoursDay, &sssSchedule, &phSchedule, &piSchedule, &taxSchedule)

	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return cfg, fmt.Errorf("load payroll group: %w", err)
	}

	cfg.Frequency = normalizeFrequency(frequency)
	cfg.CountryCode = loadInstallationCountry(ctx, db)
	cfg.StatutoryDriver = statutoryDriver(cfg.CountryCode)

	if daysYear.Valid && daysYear.Int64 > 0 {
		cfg.DaysPerYear = int(daysYear.Int64)
	}

	if hoursDay.Valid && hoursDay.Int64 > 0 {
		cfg.HoursPerDay = int(hoursDay.Int64)
	}

	cfg.SSSSchedule = statutory.NormalizeSchedule(sssSchedule.String)
	cfg.PhilHealthSchedule = statutory.NormalizeSchedule(phSchedule.String)
	cfg.PagIBIGSchedule = statutory.NormalizeSchedule(piSchedule.String)
	cfg.TaxSchedule = statutory.NormalizeSchedule(taxSchedule.String)

	if cfg.RunType == model.RunThirteenthMonth || cfg.RunType == model.RunAnnualAdjustment {
		// 13th-month and annual adjustment runs are standalone: no attendance
		// or regular statutory contributions.
		cfg.PayWithoutTimesheet = false
	}

	settings := loadSettings(ctx, db, run.CompanyID)

	if value, ok := settings["auto_absent_no_timesheet"]; ok {
		cfg.PayWithoutTimesheet = !isTruthy(value)
	}

	if value, ok := settings["days_year_no_weeked"]; ok {
		if days, err := strconv.Atoi(strings.TrimSpace(value)); err == nil && days > 0 {
			cfg.DaysPerYear = days
		}
	}

	if value, ok := settings["pay_unworked_regular_holidays"]; ok {
		cfg.PayUnworkedRegularHolidays = isTruthy(value)
	}

	if value := strings.TrimSpace(settings["sss_base"]); value != "" {
		cfg.SssBase = value
	}

	if value := strings.TrimSpace(settings["phil_health_base"]); value != "" {
		cfg.PhilHealthBase = value
	}

	if value := strings.TrimSpace(settings["hdmf_base"]); value != "" {
		cfg.PagIBIGBase = value
	}

	if value := strings.TrimSpace(settings["gsis_base"]); value != "" {
		cfg.GSISBase = value
	}

	// A government agency remits GSIS instead of SSS (RA 8291).
	cfg.IsGovernment = isGovernmentCompany(ctx, db, run.CompanyID)
	cfg.Sector = "Private"
	if cfg.IsGovernment {
		cfg.Sector = "Government"
	}

	if cfg.StatutoryDriver == "ph" {
		if cfg.SSSBrackets, err = loadSSSBrackets(ctx, db, run); err != nil {
			return cfg, err
		}

		if cfg.PhilHealth, err = loadPhilHealth(ctx, db, run, cfg.PhilHealth); err != nil {
			return cfg, err
		}

		if cfg.PagIBIG, err = loadPagIBIG(ctx, db, run, cfg.PagIBIG); err != nil {
			return cfg, err
		}

		// Only a government agency needs the GSIS schedule; a private payroll
		// must not fail on an unimported table it never uses.
		if cfg.IsGovernment {
			if cfg.GSIS, err = loadGSIS(ctx, db, run, cfg.GSIS); err != nil {
				return cfg, err
			}
		}

		if cfg.TaxBrackets, err = loadTaxBrackets(ctx, db, run, cfg.Frequency); err != nil {
			return cfg, err
		}

		if cfg.AnnualTaxBrackets, err = loadTaxBrackets(ctx, db, run, statutory.FrequencyMonthly); err != nil {
			return cfg, err
		}

		cfg.MinimumWage = loadMinimumWage(ctx, db, run)
		cfg.MinimumWages = loadMinimumWages(ctx, db, run)
		cfg.DefaultRegion = "NCR"
		cfg.DefaultIndustry = "NON-AGRICULTURE"
		cfg.DeMinimis = loadDeMinimis(ctx, db, run)
		cfg.TaxBrackets = ensureTaxBrackets(cfg.TaxBrackets, cfg.Frequency)
	} else {
		if cfg.Components, err = loadGenericComponents(ctx, db, run, cfg); err != nil {
			return cfg, err
		}

		if len(cfg.Components) == 0 {
			return cfg, fmt.Errorf(
				"statutory components do not cover the payroll date: no active %s components are configured",
				cfg.CountryCode,
			)
		}
	}

	cfg.Holidays = loadHolidays(ctx, db, run)

	return cfg, nil
}

// loadInstallationCountry returns the installation country, defaulting to PH
// on databases that have not run the installer.
func loadInstallationCountry(ctx context.Context, db *sql.DB) string {
	if !tableExists(ctx, db, "installation_settings") {
		return "PH"
	}

	var country sql.NullString

	err := db.QueryRowContext(ctx,
		"SELECT `value` FROM installation_settings WHERE `key` = 'country_code' LIMIT 1").Scan(&country)
	if err != nil || !country.Valid || strings.TrimSpace(country.String) == "" {
		return "PH"
	}

	return strings.ToUpper(strings.TrimSpace(country.String))
}

// statutoryDriver maps a country to its computation strategy. A future
// jurisdiction with a dedicated driver is added here on both engines.
func statutoryDriver(country string) string {
	if strings.EqualFold(strings.TrimSpace(country), "PH") {
		return "ph"
	}

	return "generic"
}

// loadGenericComponents resolves the active components for a generic country,
// letting a company row override the country default with the same code.
func loadGenericComponents(ctx context.Context, db *sql.DB, run Run, cfg model.Config) ([]statutory.GenericComponent, error) {
	if !tableExists(ctx, db, "statutory_components") {
		return nil, nil
	}

	sector := "private"
	if cfg.IsGovernment {
		sector = "government"
	}

	rows, err := db.QueryContext(ctx, `
		SELECT id, company_id, code, name, category, applies_to, calculation, base,
		       employee_rate, employer_rate,
		       employee_amount_centavos, employer_amount_centavos,
		       min_base_centavos, max_base_centavos, max_employee_centavos, max_employer_centavos,
		       rounding, schedule, reduces_taxable_income, sort_order
		FROM statutory_components
		WHERE country_code = ?
		  AND is_active = 1
		  AND (company_id IS NULL OR company_id = ?)
		  AND (effective_from IS NULL OR effective_from <= ?)
		  AND (effective_to IS NULL OR effective_to >= ?)
		  AND (sector = '' OR sector = 'any' OR sector = ?)
		ORDER BY sort_order, code`,
		cfg.CountryCode, run.CompanyID, run.PayrollDate, run.PayrollDate, sector)
	if err != nil {
		return nil, fmt.Errorf("load statutory_components: %w", err)
	}

	defer rows.Close()

	type selected struct {
		component statutory.GenericComponent
		id        int64
		company   bool
	}

	byCode := map[string]selected{}

	for rows.Next() {
		var (
			id                              int64
			companyID                       sql.NullInt64
			code, name, category, appliesTo string
			calculation, base, rounding     string
			schedule                        string
			employeeRate, employerRate      sql.NullFloat64
			employeeAmount, employerAmount  sql.NullInt64
			minBase, maxBase                sql.NullInt64
			maxEmployee, maxEmployer        sql.NullInt64
			reducesTaxable                  bool
			sortOrder                       int
		)

		if err := rows.Scan(
			&id, &companyID, &code, &name, &category, &appliesTo, &calculation, &base,
			&employeeRate, &employerRate,
			&employeeAmount, &employerAmount,
			&minBase, &maxBase, &maxEmployee, &maxEmployer,
			&rounding, &schedule, &reducesTaxable, &sortOrder,
		); err != nil {
			return nil, fmt.Errorf("scan statutory component: %w", err)
		}

		key := strings.ToLower(strings.TrimSpace(code))
		companyScoped := companyID.Valid

		if existing, ok := byCode[key]; ok && existing.company && !companyScoped {
			continue
		}

		byCode[key] = selected{
			id:      id,
			company: companyScoped,
			component: statutory.GenericComponent{
				Code:                 code,
				Name:                 name,
				Category:             category,
				AppliesTo:            appliesTo,
				Calculation:          calculation,
				Base:                 base,
				EmployeeRate:         employeeRate.Float64,
				EmployerRate:         employerRate.Float64,
				EmployeeAmount:       money.Centavos(employeeAmount.Int64),
				EmployerAmount:       money.Centavos(employerAmount.Int64),
				MinBase:              money.Centavos(minBase.Int64),
				MaxBase:              money.Centavos(maxBase.Int64),
				MaxEmployee:          money.Centavos(maxEmployee.Int64),
				MaxEmployer:          money.Centavos(maxEmployer.Int64),
				Rounding:             rounding,
				Schedule:             statutory.NormalizeSchedule(schedule),
				ReducesTaxableIncome: reducesTaxable,
				SortOrder:            sortOrder,
			},
		}
	}

	if err := rows.Err(); err != nil {
		return nil, err
	}

	if len(byCode) == 0 {
		return nil, nil
	}

	ids := make([]int64, 0, len(byCode))

	for _, item := range byCode {
		ids = append(ids, item.id)
	}

	bands, err := loadGenericBands(ctx, db, ids)
	if err != nil {
		return nil, err
	}

	components := make([]statutory.GenericComponent, 0, len(byCode))

	for _, item := range byCode {
		component := item.component
		component.Bands = bands[item.id]
		components = append(components, component)
	}

	sort.Slice(components, func(i, j int) bool {
		if components[i].SortOrder == components[j].SortOrder {
			return components[i].Code < components[j].Code
		}

		return components[i].SortOrder < components[j].SortOrder
	})

	return components, nil
}

func loadGenericBands(ctx context.Context, db *sql.DB, ids []int64) (map[int64][]statutory.GenericBand, error) {
	out := map[int64][]statutory.GenericBand{}

	if len(ids) == 0 || !tableExists(ctx, db, "statutory_component_bands") {
		return out, nil
	}

	placeholders := make([]string, len(ids))
	args := make([]any, len(ids))

	for i, id := range ids {
		placeholders[i] = "?"
		args[i] = id
	}

	rows, err := db.QueryContext(ctx, `
		SELECT statutory_component_id, from_centavos, to_centavos, base_centavos,
		       employee_rate, employer_rate, employee_amount_centavos, employer_amount_centavos
		FROM statutory_component_bands
		WHERE statutory_component_id IN (`+strings.Join(placeholders, ",")+`)
		ORDER BY from_centavos, id`, args...)
	if err != nil {
		return nil, fmt.Errorf("load statutory_component_bands: %w", err)
	}

	defer rows.Close()

	for rows.Next() {
		var (
			componentID    int64
			from, base     int64
			to             sql.NullInt64
			employeeRate   sql.NullFloat64
			employerRate   sql.NullFloat64
			employeeAmount int64
			employerAmount int64
		)

		if err := rows.Scan(&componentID, &from, &to, &base, &employeeRate, &employerRate, &employeeAmount, &employerAmount); err != nil {
			return nil, fmt.Errorf("scan statutory component band: %w", err)
		}

		out[componentID] = append(out[componentID], statutory.GenericBand{
			From:           money.Centavos(from),
			To:             money.Centavos(to.Int64),
			Base:           money.Centavos(base),
			EmployeeRate:   employeeRate.Float64,
			EmployerRate:   employerRate.Float64,
			EmployeeAmount: money.Centavos(employeeAmount),
			EmployerAmount: money.Centavos(employerAmount),
		})
	}

	return out, rows.Err()
}

func normalizeFrequency(value string) statutory.Frequency {
	value = strings.ToLower(strings.TrimSpace(value))

	switch value {
	case "monthly", "month":
		return statutory.FrequencyMonthly
	default:
		return statutory.FrequencySemiMonthly
	}
}

func loadSettings(ctx context.Context, db *sql.DB, companyID int64) map[string]string {
	settings := map[string]string{}

	rows, err := db.QueryContext(ctx,
		"SELECT `key`, value FROM settings WHERE company_id = ?", companyID)
	if err != nil {
		return settings
	}

	defer rows.Close()

	for rows.Next() {
		var key string
		var value sql.NullString

		if err := rows.Scan(&key, &value); err == nil {
			settings[key] = value.String
		}
	}

	return settings
}

func isTruthy(value string) bool {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "1", "true", "yes", "on":
		return true
	default:
		return false
	}
}

func loadEmployees(ctx context.Context, db *sql.DB, run Run, cfg model.Config) ([]model.Employee, error) {
	rows, err := db.QueryContext(ctx, `
		SELECT e.id, COALESCE(e.employee_number, ''), TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))),
		       COALESCE(e.basic_salary, 0), COALESCE(e.daily_rate, 0), COALESCE(e.tax_status, ''), COALESCE(e.dependents, '0'),
		       COALESCE(e.payment_type, 'monthly'), COALESCE(e.is_monday_friday, 1), COALESCE(e.is_minimum_wage, 0),
		       COALESCE(e.rest_day_pay, 0), COALESCE(e.regular_holiday_pay, 0), COALESCE(e.special_holiday_pay, 0),
		       COALESCE(e.premium_holiday_pay, 0), COALESCE(e.regular_holiday_special_holiday_pay, 0),
		       COALESCE(e.overtime_pay, 0), COALESCE(e.night_differential, 0), COALESCE(e.deminimis, 0),
		       COALESCE(DATE_FORMAT(e.date_hired, '%Y-%m-%d'), ''),
		       COALESCE(DATE_FORMAT(e.date_resigned, '%Y-%m-%d'), ''),
		       COALESCE(e.total_vacation_leave, 0) + COALESCE(e.total_sick_leave, 0),
		       COALESCE(NULLIF(e.region, ''), o.region, ''),
		       COALESCE(NULLIF(e.industry, ''), o.industry, '')
		FROM employees e
		LEFT JOIN offices o ON o.id = e.office_id
		WHERE e.company_id = ? AND e.payroll_group_id = ? AND e.deleted_at IS NULL
		ORDER BY e.id`, run.CompanyID, run.GroupID)
	if err != nil {
		return nil, fmt.Errorf("load employees: %w", err)
	}

	defer rows.Close()

	employees := []model.Employee{}
	index := map[int64]int{}

	for rows.Next() {
		var (
			employee    model.Employee
			basicSalary float64
			dailyRate   float64
			dependents  sql.NullString
			leaveDays   float64
			isMonday    bool
			isMWE       bool
			rd, rh, sh  bool
			ph, rhsh    bool
			ot, nd, dem bool
		)

		if err := rows.Scan(&employee.ID, &employee.Number, &employee.Name,
			&basicSalary, &dailyRate, &employee.TaxStatus, &dependents,
			&employee.PaymentType, &isMonday, &isMWE,
			&rd, &rh, &sh, &ph, &rhsh, &ot, &nd, &dem,
			&employee.HiredOn, &employee.SeparatedOn, &leaveDays,
			&employee.Region, &employee.Industry); err != nil {
			return nil, fmt.Errorf("scan employee: %w", err)
		}

		// Salary columns are stored in centavos, like payroll_details.
		employee.MonthlyBasic = money.Centavos(int64(basicSalary))
		employee.DailyRate = money.Centavos(int64(dailyRate))
		employee.IsMondayFriday = isMonday
		employee.IsMinimumWage = isMWE
		employee.LeaveBalanceDays = leaveDays
		employee.PaymentType = strings.ToLower(strings.TrimSpace(employee.PaymentType))
		if employee.PaymentType == "" {
			employee.PaymentType = model.PaymentMonthly
		}

		employee.Dependents, _ = strconv.Atoi(strings.TrimSpace(dependents.String))

		employee.Benefits = model.BenefitFlags{
			RestDayPay:                      rd,
			RegularHolidayPay:               rh,
			SpecialHolidayPay:               sh,
			PremiumHolidayPay:               ph,
			RegularHolidaySpecialHolidayPay: rhsh,
			OvertimePay:                     ot,
			NightDifferential:               nd,
			DeMinimis:                       dem,
		}

		if employee.IsMinimumWage {
			employee.MinimumWage = resolveMinimumWage(cfg, employee)
			if employee.MinimumWage == 0 {
				employee.MinimumWage = cfg.MinimumWage
			}
		}

		index[employee.ID] = len(employees)
		employees = append(employees, employee)
	}

	if err := rows.Err(); err != nil {
		return nil, err
	}

	if len(employees) == 0 {
		return employees, nil
	}

	if err := loadTimesheets(ctx, db, run, employees, index); err != nil {
		return nil, err
	}

	if err := loadCompensations(ctx, db, run, cfg, employees, index); err != nil {
		return nil, err
	}

	if err := loadDeductions(ctx, db, run, employees, index); err != nil {
		return nil, err
	}

	if err := loadLoans(ctx, db, run, cfg, employees, index); err != nil {
		return nil, err
	}

	if err := loadYTD(ctx, db, run, employees); err != nil {
		return nil, err
	}

	applyProration(cfg, employees)

	return employees, nil
}

// applyProration scales earnings flagged is_prorated by the attendance ratio.
func applyProration(cfg model.Config, employees []model.Employee) {
	periods := cfg.PeriodsPerMonth()
	expectedDays := float64(cfg.DaysPerYear) / 12 / float64(periods)
	expectedHours := expectedDays * float64(cfg.HoursPerDay)

	for i := range employees {
		employee := &employees[i]

		workedDays := 0.0
		workedHours := 0.0

		for _, line := range employee.Timesheets {
			if line.Absent || line.Hours <= 0 && !line.PaidLeave && !line.Leave {
				continue
			}

			workedDays++
			workedHours += line.Hours + line.Overtime
		}

		ratioFor := func(by string) float64 {
			if strings.EqualFold(by, "hour") && expectedHours > 0 {
				return clampRatio(workedHours / expectedHours)
			}

			if expectedDays > 0 {
				return clampRatio(workedDays / expectedDays)
			}

			return 1
		}

		for _, list := range []*[]model.Earning{&employee.Allowances, &employee.Compensations} {
			for j := range *list {
				if !(*list)[j].Prorated {
					continue
				}

				(*list)[j].Amount = (*list)[j].Amount.Mul(ratioFor((*list)[j].ProratedBy))
			}
		}
	}
}

func clampRatio(value float64) float64 {
	if value < 0 {
		return 0
	}

	if value > 1 {
		return 1
	}

	return value
}

func loadTimesheets(ctx context.Context, db *sql.DB, run Run, employees []model.Employee, index map[int64]int) error {
	rows, err := db.QueryContext(ctx, `
		SELECT employee_id, DATE(log_date), COALESCE(day_type, ''), COALESCE(is_absent, 0),
		       COALESCE(is_leave, 0), COALESCE(is_paid_leave, 0),
		       COALESCE(actual_regular, 0), COALESCE(actual_ot, 0),
		       COALESCE(actual_nt, 0), COALESCE(actual_nt_ot, 0),
		       COALESCE(regular, 0), COALESCE(ot, 0), COALESCE(nt, 0)
		FROM timesheets
		WHERE deleted_at IS NULL
		  AND employee_id IN (SELECT id FROM employees WHERE company_id = ? AND payroll_group_id = ? AND deleted_at IS NULL)
		  AND DATE(log_date) BETWEEN ? AND ?`,
		run.CompanyID, run.GroupID, run.DateFrom, run.DateTo)
	if err != nil {
		return fmt.Errorf("load timesheets: %w", err)
	}

	defer rows.Close()

	for rows.Next() {
		var (
			employeeID int64
			logDate    time.Time
			dayType    string
			absent     int
			leave      int
			paidLeave  int
			regHours   float64
			otHours    float64
			ntHours    float64
			ntOTHours  float64
			regularPay float64
			otPay      float64
			ntPay      float64
		)

		if err := rows.Scan(&employeeID, &logDate, &dayType, &absent, &leave, &paidLeave,
			&regHours, &otHours, &ntHours, &ntOTHours, &regularPay, &otPay, &ntPay); err != nil {
			return fmt.Errorf("scan timesheet: %w", err)
		}

		position, ok := index[employeeID]
		if !ok {
			continue
		}

		hours := regHours / 100
		overtime := otHours / 100
		night := ntHours / 100
		nightOT := ntOTHours / 100
		legacy := false

		// Legacy rows stored premium-equivalent hours in regular/ot/nt rather
		// than the actual_* buckets. Reconstruct the ordinary hours instead of
		// reading them as money (which paid ₱8.00 for an 8-hour day).
		if hours == 0 && overtime == 0 && night == 0 && nightOT == 0 &&
			(regularPay != 0 || otPay != 0 || ntPay != 0) {
			hours = regularPay / 100
			legacy = true
		}

		// Night OT is stored inside actual_nt as well; keep the buckets
		// disjoint so the 10% differential is not paid twice.
		ordinaryNight := night - nightOT
		if ordinaryNight < 0 {
			ordinaryNight = 0
		}

		employees[position].Timesheets = append(employees[position].Timesheets, model.TimesheetLine{
			Date:        logDate.Format("2006-01-02"),
			DayType:     dayType,
			Hours:       hours,
			Overtime:    overtime + nightOT,
			Night:       ordinaryNight,
			NightOT:     nightOT,
			Absent:      absent == 1,
			Leave:       leave == 1,
			PaidLeave:   paidLeave == 1,
			LegacyHours: legacy,
		})
	}

	return rows.Err()
}

func frequencyApplies(frequency string, run Run, cfg model.Config) bool {
	trimmed := strings.TrimSpace(frequency)
	if trimmed == "" {
		return true
	}

	schedule := statutory.NormalizeSchedule(trimmed)

	return statutory.Divisor(schedule, cfg.Frequency, run.Cutoff) > 0
}

func truncateDay(value time.Time) time.Time {
	return time.Date(value.Year(), value.Month(), value.Day(), 0, 0, 0, 0, value.Location())
}

func loadCompensations(ctx context.Context, db *sql.DB, run Run, cfg model.Config, employees []model.Employee, index map[int64]int) error {
	if !tableExists(ctx, db, "employees_compensations") || !tableExists(ctx, db, "compensations") {
		return nil
	}

	rows, err := db.QueryContext(ctx, `
		SELECT ec.employee_id, COALESCE(ec.amount, 0),
		       COALESCE(ec.is_taxable, COALESCE(c.is_taxable, 1)),
		       COALESCE(ec.is_active, 1), COALESCE(ec.frequency, ''),
		       COALESCE(DATE_FORMAT(ec.valid_date_from, '%Y-%m-%d'), ''),
		       COALESCE(DATE_FORMAT(ec.valid_date_to, '%Y-%m-%d'), ''),
		       COALESCE(ec.is_prorated, 0), COALESCE(ec.prorated_by, ''),
		       COALESCE(DATE_FORMAT(ec.release_date, '%Y-%m-%d'), ''),
		       COALESCE(ec.status, ''), COALESCE(c.code, ''), COALESCE(c.name, ''),
		       COALESCE(c.group, ''), COALESCE(c.maximum_non_tax, 0),
		       COALESCE(ec.is_special, 0)
		FROM employees_compensations ec
		LEFT JOIN compensations c ON c.id = ec.compensation_id
		WHERE ec.deleted_at IS NULL AND ec.company_id = ?
		  AND ec.employee_id IN (SELECT id FROM employees WHERE company_id = ? AND payroll_group_id = ? AND deleted_at IS NULL)`,
		run.CompanyID, run.CompanyID, run.GroupID)
	if err != nil {
		return fmt.Errorf("load compensations: %w", err)
	}

	defer rows.Close()

	for rows.Next() {
		var (
			employeeID int64
			amount     float64
			taxable    bool
			active     bool
			frequency  string
			from       string
			to         string
			prorated   bool
			proratedBy string
			release    string
			status     string
			code       string
			name       string
			group      string
			maxNonTax  float64
			special    bool
		)

		if err := rows.Scan(&employeeID, &amount, &taxable, &active, &frequency, &from, &to,
			&prorated, &proratedBy, &release, &status, &code, &name, &group, &maxNonTax, &special); err != nil {
			return fmt.Errorf("scan compensation: %w", err)
		}

		position, ok := index[employeeID]
		if !ok || !active || strings.EqualFold(status, "cancelled") {
			continue
		}

		earning := model.Earning{
			Code:              code,
			Name:              name,
			Amount:            money.Centavos(int64(amount)),
			Taxable:           taxable,
			NonTaxableCeiling: money.Centavos(int64(maxNonTax)),
			Prorated:          prorated,
			ProratedBy:        proratedBy,
		}

		switch {
		case strings.EqualFold(group, "Allowance"):
			if !validDates(from, to, run) {
				continue
			}

			// A special assignment is released once, in the period that
			// contains its release date; a recurring one follows the payment
			// schedule it was given.
			if special {
				if !releaseWithinPeriod(release, run) {
					continue
				}
			} else if !frequencyApplies(frequency, run, cfg) {
				continue
			}

			earning.Kind = model.KindAllowance
			employees[position].Allowances = append(employees[position].Allowances, earning)
		case strings.EqualFold(group, "Bonus"):
			if !releaseWithinPeriod(release, run) {
				continue
			}

			earning.Kind = model.KindBonus
			employees[position].Bonuses = append(employees[position].Bonuses, earning)
		case strings.EqualFold(group, "Commission"):
			if !releaseWithinPeriod(release, run) {
				continue
			}

			earning.Kind = model.KindCommission
			employees[position].Commissions = append(employees[position].Commissions, earning)
		default:
			if !validDates(from, to, run) || !frequencyApplies(frequency, run, cfg) {
				continue
			}

			earning.Kind = model.KindCompensation
			employees[position].Compensations = append(employees[position].Compensations, earning)
		}
	}

	return rows.Err()
}

func releaseWithinPeriod(value string, run Run) bool {
	if value == "" {
		return true
	}

	date, err := time.Parse("2006-01-02", value)
	if err != nil {
		return true
	}

	return !date.Before(truncateDay(run.DateFrom)) && !date.After(truncateDay(run.DateTo))
}

func loadDeductions(ctx context.Context, db *sql.DB, run Run, employees []model.Employee, index map[int64]int) error {
	if !tableExists(ctx, db, "employees_deductions") {
		return nil
	}

	codeExpr := "''"
	calculationExpr := "'fixed'"
	rateExpr := "0"
	baseExpr := "''"
	reducesExpr := "0"

	for column, expression := range map[string]*string{
		"code":                   &codeExpr,
		"calculation":            &calculationExpr,
		"rate":                   &rateExpr,
		"base":                   &baseExpr,
		"reduces_taxable_income": &reducesExpr,
	} {
		if columnExists(ctx, db, "deductions", column) {
			*expression = "COALESCE(d.`" + column + "`, " + *expression + ")"
		}
	}

	rows, err := db.QueryContext(ctx, fmt.Sprintf(`
		SELECT ed.employee_id, COALESCE(d.name, ''), COALESCE(ed.amount, 0),
		       COALESCE(ed.frequency, ''),
		       COALESCE(DATE_FORMAT(ed.valid_date_from, '%%Y-%%m-%%d'), ''),
		       COALESCE(DATE_FORMAT(ed.valid_date_to, '%%Y-%%m-%%d'), ''),
		       %s, %s, %s, %s, %s
		FROM employees_deductions ed
		LEFT JOIN deductions d ON d.id = ed.deduction_id
		WHERE ed.deleted_at IS NULL AND ed.company_id = ?
		  AND ed.employee_id IN (SELECT id FROM employees WHERE company_id = ? AND payroll_group_id = ? AND deleted_at IS NULL)`,
		codeExpr, calculationExpr, rateExpr, baseExpr, reducesExpr),
		run.CompanyID, run.CompanyID, run.GroupID)
	if err != nil {
		return fmt.Errorf("load deductions: %w", err)
	}

	defer rows.Close()

	for rows.Next() {
		var (
			employeeID  int64
			name        string
			amount      float64
			frequency   string
			from        string
			to          string
			code        string
			calculation string
			rate        float64
			basis       string
			reduces     bool
		)

		if err := rows.Scan(&employeeID, &name, &amount, &frequency, &from, &to, &code, &calculation, &rate, &basis, &reduces); err != nil {
			return fmt.Errorf("scan deduction: %w", err)
		}

		position, ok := index[employeeID]
		if !ok || !validDates(from, to, run) {
			continue
		}

		employees[position].Deductions = append(employees[position].Deductions, model.Earning{
			Code:                 code,
			Name:                 name,
			Kind:                 model.KindDeduction,
			Amount:               money.Centavos(int64(amount)),
			Taxable:              false,
			Calculation:          calculation,
			Rate:                 rate,
			Basis:                basis,
			ReducesTaxableIncome: reduces,
		})
	}

	return rows.Err()
}

func loadLoans(ctx context.Context, db *sql.DB, run Run, cfg model.Config, employees []model.Employee, index map[int64]int) error {
	if !tableExists(ctx, db, "employees_loans") {
		return nil
	}

	rows, err := db.QueryContext(ctx, `
		SELECT el.employee_id, COALESCE(l.code, ''), COALESCE(l.name, ''), COALESCE(el.monthly_amortization, 0),
		       COALESCE(el.balance, 0), COALESCE(el.scheme, ''),
		       COALESCE(DATE_FORMAT(el.repayment_start_date, '%Y-%m-%d'), ''),
		       COALESCE(DATE_FORMAT(el.repayment_end_date, '%Y-%m-%d'), ''),
		       COALESCE(el.is_active, 1)
		FROM employees_loans el
		LEFT JOIN loans l ON l.id = el.loan_id
		WHERE el.deleted_at IS NULL AND el.company_id = ?
		  AND el.employee_id IN (SELECT id FROM employees WHERE company_id = ? AND payroll_group_id = ? AND deleted_at IS NULL)`,
		run.CompanyID, run.CompanyID, run.GroupID)
	if err != nil {
		return fmt.Errorf("load loans: %w", err)
	}

	defer rows.Close()

	for rows.Next() {
		var (
			employeeID   int64
			code         string
			name         string
			amortisation float64
			balance      float64
			scheme       string
			start        string
			end          string
			active       bool
		)

		if err := rows.Scan(&employeeID, &code, &name, &amortisation, &balance, &scheme, &start, &end, &active); err != nil {
			return fmt.Errorf("scan loan: %w", err)
		}

		position, ok := index[employeeID]
		if !ok || !active || !validDates(start, end, run) {
			continue
		}

		if scheme != "" && !frequencyApplies(scheme, run, cfg) {
			continue
		}

		amount := splitCentavos(money.Centavos(int64(amortisation)), cfg.PeriodsPerMonth())
		if balance > 0 && amount > money.Centavos(int64(balance)) {
			amount = money.Centavos(int64(balance))
		}

		employees[position].Loans = append(employees[position].Loans, model.Earning{
			Code:   code,
			Name:   name,
			Kind:   model.KindLoan,
			Amount: amount,
		})
	}

	return rows.Err()
}

func splitCentavos(amount money.Centavos, parts int) money.Centavos {
	if parts <= 1 {
		return amount
	}

	return amount.Split(parts)[0]
}

func loadYTD(ctx context.Context, db *sql.DB, run Run, employees []model.Employee) error {
	if !tableExists(ctx, db, "employee_payroll_ytd") {
		return nil
	}

	rows, err := db.QueryContext(ctx, `
		SELECT employee_id, basic_earned, gross_earned, taxable_income, tax_withheld,
		       contributions, thirteenth_month_paid, other_benefits
		FROM employee_payroll_ytd
		WHERE company_id = ? AND year = ?
		  AND employee_id IN (SELECT id FROM employees WHERE company_id = ? AND payroll_group_id = ? AND deleted_at IS NULL)`,
		run.CompanyID, run.PayrollDate.Year(), run.CompanyID, run.GroupID)
	if err != nil {
		return fmt.Errorf("load ytd: %w", err)
	}

	defer rows.Close()

	for rows.Next() {
		var (
			employeeID int64
			ytd        model.YtdTotals
		)

		if err := rows.Scan(&employeeID, &ytd.BasicEarned, &ytd.GrossEarned, &ytd.TaxableIncome,
			&ytd.TaxWithheld, &ytd.Contributions, &ytd.ThirteenthMonthPaid, &ytd.OtherBenefits); err != nil {
			return fmt.Errorf("scan ytd: %w", err)
		}

		for i := range employees {
			if employees[i].ID == employeeID {
				ytd.Year = run.PayrollDate.Year()
				employees[i].YTD = ytd

				break
			}
		}
	}

	if err := rows.Err(); err != nil {
		return err
	}

	return loadYTDDeMinimis(ctx, db, run, employees)
}

// loadYTDDeMinimis loads the exemption already used in each employee's
// current month, semester and year.
func loadYTDDeMinimis(ctx context.Context, db *sql.DB, run Run, employees []model.Employee) error {
	if !tableExists(ctx, db, "employee_payroll_ytd_de_minimis") {
		return nil
	}

	rows, err := db.QueryContext(ctx, `
		SELECT employee_id, period, code, exempt_centavos
		FROM employee_payroll_ytd_de_minimis
		WHERE company_id = ? AND year = ?
		  AND employee_id IN (SELECT id FROM employees WHERE company_id = ? AND payroll_group_id = ? AND deleted_at IS NULL)`,
		run.CompanyID, run.PayrollDate.Year(), run.CompanyID, run.GroupID)
	if err != nil {
		return fmt.Errorf("load ytd de minimis: %w", err)
	}

	defer rows.Close()

	for rows.Next() {
		var (
			employeeID int64
			period     string
			code       string
			exempt     int64
		)

		if err := rows.Scan(&employeeID, &period, &code, &exempt); err != nil {
			return fmt.Errorf("scan ytd de minimis: %w", err)
		}

		for i := range employees {
			if employees[i].ID != employeeID {
				continue
			}

			if employees[i].YTD.DeMinimis == nil {
				employees[i].YTD.DeMinimis = map[string]money.Centavos{}
			}

			key := strings.ToLower(strings.TrimSpace(code)) + "|" + period
			employees[i].YTD.DeMinimis[key] = money.Centavos(exempt)

			break
		}
	}

	if err := rows.Err(); err != nil {
		return err
	}

	// The current payroll's own earlier run is already in the ledger. Take it
	// back out so re-processing starts from where it did the first time.
	return subtractCurrentRunDeMinimis(ctx, db, run, employees)
}

// subtractCurrentRunDeMinimis removes the payroll's own prior consumption from
// the loaded ledger.
func subtractCurrentRunDeMinimis(ctx context.Context, db *sql.DB, run Run, employees []model.Employee) error {
	var hasColumn int
	if err := db.QueryRowContext(ctx, `
		SELECT COUNT(*) FROM information_schema.columns
		WHERE table_schema = DATABASE() AND table_name = 'payroll_details'
		  AND column_name = 'de_minimis_exempt'`).Scan(&hasColumn); err != nil || hasColumn == 0 {
		return nil
	}

	rows, err := db.QueryContext(ctx, `
		SELECT employee_id, de_minimis_exempt
		FROM payroll_details
		WHERE payroll_id = ? AND company_id = ? AND deleted_at IS NULL`,
		run.PayrollID, run.CompanyID)
	if err != nil {
		return fmt.Errorf("load current de minimis ytd: %w", err)
	}

	defer rows.Close()

	for rows.Next() {
		var (
			employeeID int64
			raw        sql.NullString
		)

		if err := rows.Scan(&employeeID, &raw); err != nil {
			return fmt.Errorf("scan current de minimis ytd: %w", err)
		}

		if !raw.Valid || raw.String == "" {
			continue
		}

		usage := map[string]int64{}
		if err := json.Unmarshal([]byte(raw.String), &usage); err != nil {
			continue
		}

		for i := range employees {
			if employees[i].ID != employeeID {
				continue
			}

			if employees[i].YTD.DeMinimis == nil {
				employees[i].YTD.DeMinimis = map[string]money.Centavos{}
			}

			for key, amount := range usage {
				remaining := int64(employees[i].YTD.DeMinimis[key]) - amount
				if remaining < 0 {
					remaining = 0
				}

				employees[i].YTD.DeMinimis[key] = money.Centavos(remaining)
			}

			break
		}
	}

	return rows.Err()
}

func validDates(from, to string, run Run) bool {
	if from != "" {
		date, err := time.Parse("2006-01-02", from)
		if err == nil && date.After(truncateDay(run.DateTo)) {
			return false
		}
	}

	if to != "" {
		date, err := time.Parse("2006-01-02", to)
		if err == nil && date.Before(truncateDay(run.DateFrom)) {
			return false
		}
	}

	return true
}

func slug(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	value = strings.ReplaceAll(value, "-", "_")
	value = strings.ReplaceAll(value, " ", "_")

	return value
}

func loadHolidays(ctx context.Context, db *sql.DB, run Run) []model.Holiday {
	if !tableExists(ctx, db, "holidays") {
		return nil
	}

	rows, err := db.QueryContext(ctx, `
		SELECT DATE_FORMAT(date, '%Y-%m-%d'), COALESCE(code, '')
		FROM holidays
		WHERE deleted_at IS NULL AND company_id = ? AND date BETWEEN ? AND ?
		ORDER BY date`, run.CompanyID, run.DateFrom, run.DateTo)
	if err != nil {
		return nil
	}

	defer rows.Close()

	holidays := []model.Holiday{}

	for rows.Next() {
		var holiday model.Holiday

		if err := rows.Scan(&holiday.Date, &holiday.Code); err == nil && holiday.Code != "" {
			holidays = append(holidays, holiday)
		}
	}

	return holidays
}

func loadDeMinimis(ctx context.Context, db *sql.DB, run Run) []model.DeMinimisBenefit {
	if !tableExists(ctx, db, "statutory_de_minimis") {
		return defaultDeMinimis()
	}

	basisExpr := "'" + model.DeMinimisMonthly + "'"
	if columnExists(ctx, db, "statutory_de_minimis", "basis") {
		basisExpr = "COALESCE(NULLIF(basis, ''), '" + model.DeMinimisMonthly + "')"
	}

	percentageExpr := "0"
	if columnExists(ctx, db, "statutory_de_minimis", "percentage") {
		percentageExpr = "COALESCE(percentage, 0)"
	}

	rows, err := db.QueryContext(ctx, fmt.Sprintf(`
		SELECT code, ceiling_centavos, %s, %s
		FROM statutory_de_minimis
		WHERE country_code = ?
		  AND (company_id IS NULL OR company_id = ?)
		  AND (effective_from IS NULL OR effective_from <= ?)
		  AND (effective_to IS NULL OR effective_to >= ?)
		ORDER BY code, effective_from DESC`, basisExpr, percentageExpr), "PH", run.CompanyID, run.PayrollDate, run.PayrollDate)
	if err != nil {
		return defaultDeMinimis()
	}

	defer rows.Close()

	benefits := []model.DeMinimisBenefit{}

	for rows.Next() {
		var benefit model.DeMinimisBenefit

		if err := rows.Scan(&benefit.Code, &benefit.Ceiling, &benefit.Basis, &benefit.Percentage); err == nil {
			if benefit.Basis == "" {
				benefit.Basis = model.DeMinimisMonthly
			}

			benefits = append(benefits, benefit)
		}
	}

	if len(benefits) == 0 {
		return defaultDeMinimis()
	}

	return benefits
}

// defaultDeMinimis holds the RR 2-98 ceilings, as amended by RR 29-2025, used
// when the table is empty.
func defaultDeMinimis() []model.DeMinimisBenefit {
	return []model.DeMinimisBenefit{
		{Code: "rice", Ceiling: money.FromPesos(2500), Basis: model.DeMinimisMonthly},
		{Code: "clothing", Ceiling: money.FromPesos(8000), Basis: model.DeMinimisAnnual},
		{Code: "medical", Ceiling: money.FromPesos(2000), Basis: model.DeMinimisSemester},
		{Code: "laundry", Ceiling: money.FromPesos(400), Basis: model.DeMinimisMonthly},
		{Code: "meal", Ceiling: money.FromPesos(0), Basis: model.DeMinimisHourlyOT, Percentage: model.DeMinimisHourlyOTPercentage},
		{Code: "medical_assistance", Ceiling: money.FromPesos(12000), Basis: model.DeMinimisAnnual},
		{Code: "achievement_award", Ceiling: money.FromPesos(12000), Basis: model.DeMinimisAnnual},
		{Code: "gifts", Ceiling: money.FromPesos(6000), Basis: model.DeMinimisAnnual},
		{Code: "cba_productivity", Ceiling: money.FromPesos(12000), Basis: model.DeMinimisAnnual},
	}
}

// coverageError refuses to compute with a statutory table that has rows but
// none that cover the payroll date.
func coverageError(table string, run Run) error {
	return fmt.Errorf("%w: %s has no schedule effective on %s",
		ErrStatutoryCoverage, table, run.PayrollDate.Format("2006-01-02"))
}

func tableExists(ctx context.Context, db *sql.DB, table string) bool {
	var count int
	err := db.QueryRowContext(ctx,
		"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", table).
		Scan(&count)

	return err == nil && count > 0
}

func columnExists(ctx context.Context, db *sql.DB, table, column string) bool {
	var count int
	err := db.QueryRowContext(ctx,
		"SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?", table, column).
		Scan(&count)

	return err == nil && count > 0
}

func loadSSSBrackets(ctx context.Context, db *sql.DB, run Run) ([]statutory.SSSBracket, error) {
	if !tableExists(ctx, db, "statutory_sss_brackets") {
		return statutory.SSS2025Schedule(), nil
	}

	rows, err := db.QueryContext(ctx, `
		SELECT msc_from, msc_to, msc, ee, er, ec, wisp_ee, wisp_er
		FROM statutory_sss_brackets
		WHERE country_code = ?
		  AND (company_id IS NULL OR company_id = ?)
		  AND (effective_from IS NULL OR effective_from <= ?)
		  AND (effective_to IS NULL OR effective_to >= ?)
		ORDER BY msc_from`, "PH", run.CompanyID, run.PayrollDate, run.PayrollDate)
	if err != nil {
		return nil, fmt.Errorf("load statutory_sss_brackets: %w", err)
	}

	defer rows.Close()

	brackets := []statutory.SSSBracket{}

	for rows.Next() {
		var (
			bracket statutory.SSSBracket
			mscTo   sql.NullInt64
			msc     int64
			ee      int64
			er      int64
			ec      int64
			wispEE  int64
			wispER  int64
		)

		if err := rows.Scan(&bracket.MSCFrom, &mscTo, &msc, &ee, &er, &ec, &wispEE, &wispER); err != nil {
			return nil, fmt.Errorf("scan statutory_sss_brackets: %w", err)
		}

		if mscTo.Valid {
			bracket.MSCTo = money.Centavos(mscTo.Int64)
		}

		bracket.MSC = money.Centavos(msc)
		bracket.EE = money.Centavos(ee)
		bracket.ER = money.Centavos(er)
		bracket.EC = money.Centavos(ec)
		bracket.WISPEE = money.Centavos(wispEE)
		bracket.WISPER = money.Centavos(wispER)
		brackets = append(brackets, bracket)
	}

	if len(brackets) == 0 {
		return nil, coverageError("statutory_sss_brackets", run)
	}

	return brackets, nil
}

func loadPhilHealth(ctx context.Context, db *sql.DB, run Run, fallback statutory.PhilHealthConfig) (statutory.PhilHealthConfig, error) {
	if !tableExists(ctx, db, "statutory_philhealth") {
		return fallback, nil
	}

	var (
		rate    float64
		floor   int64
		ceiling int64
	)

	err := db.QueryRowContext(ctx, `
		SELECT rate, floor_centavos, ceiling_centavos
		FROM statutory_philhealth
		WHERE country_code = ?
		  AND (company_id IS NULL OR company_id = ?)
		  AND (effective_from IS NULL OR effective_from <= ?)
		  AND (effective_to IS NULL OR effective_to >= ?)
		ORDER BY effective_from DESC
		LIMIT 1`, "PH", run.CompanyID, run.PayrollDate, run.PayrollDate).
		Scan(&rate, &floor, &ceiling)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return fallback, coverageError("statutory_philhealth", run)
		}

		return fallback, fmt.Errorf("load statutory_philhealth: %w", err)
	}

	return statutory.PhilHealthConfig{
		Rate:    rate,
		Floor:   money.Centavos(floor),
		Ceiling: money.Centavos(ceiling),
	}, nil
}

func loadPagIBIG(ctx context.Context, db *sql.DB, run Run, fallback statutory.PagIBIGConfig) (statutory.PagIBIGConfig, error) {
	if !tableExists(ctx, db, "statutory_pagibig") {
		return fallback, nil
	}

	var (
		rate         float64
		lowRate      float64
		lowThreshold int64
		maxFund      int64
	)

	err := db.QueryRowContext(ctx, `
		SELECT rate, low_rate, low_threshold_centavos, max_fund_salary_centavos
		FROM statutory_pagibig
		WHERE country_code = ?
		  AND (company_id IS NULL OR company_id = ?)
		  AND (effective_from IS NULL OR effective_from <= ?)
		  AND (effective_to IS NULL OR effective_to >= ?)
		ORDER BY effective_from DESC
		LIMIT 1`, "PH", run.CompanyID, run.PayrollDate, run.PayrollDate).
		Scan(&rate, &lowRate, &lowThreshold, &maxFund)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return fallback, coverageError("statutory_pagibig", run)
		}

		return fallback, fmt.Errorf("load statutory_pagibig: %w", err)
	}

	return statutory.PagIBIGConfig{
		Rate:          rate,
		LowRate:       lowRate,
		LowThreshold:  money.Centavos(lowThreshold),
		MaxFundSalary: money.Centavos(maxFund),
	}, nil
}

// isGovernmentCompany reports whether the payroll's company is a government
// agency. Government companies are GSIS-covered, so they remit GSIS instead
// of SSS (RA 8291).
func isGovernmentCompany(ctx context.Context, db *sql.DB, companyID int64) bool {
	if !tableExists(ctx, db, "companies") || !columnExists(ctx, db, "companies", "type") {
		return false
	}

	var companyType sql.NullString
	err := db.QueryRowContext(ctx,
		"SELECT `type` FROM companies WHERE id = ?", companyID).Scan(&companyType)

	if err != nil {
		return false
	}

	return strings.EqualFold(strings.TrimSpace(companyType.String), "government")
}

func loadGSIS(ctx context.Context, db *sql.DB, run Run, fallback statutory.GSISConfig) (statutory.GSISConfig, error) {
	if !tableExists(ctx, db, "statutory_gsis") {
		return fallback, nil
	}

	var (
		employeeRate   float64
		employerRate   float64
		maxAMC         int64
		aboveLimitRate sql.NullFloat64
	)

	err := db.QueryRowContext(ctx, `
		SELECT employee_rate, employer_rate, max_amc_limit_centavos, above_limit_employee_rate
		FROM statutory_gsis
		WHERE country_code = ?
		  AND (company_id IS NULL OR company_id = ?)
		  AND (effective_from IS NULL OR effective_from <= ?)
		  AND (effective_to IS NULL OR effective_to >= ?)
		ORDER BY effective_from DESC
		LIMIT 1`,
		"PH", run.CompanyID, run.PayrollDate, run.PayrollDate).
		Scan(&employeeRate, &employerRate, &maxAMC, &aboveLimitRate)

	if errors.Is(err, sql.ErrNoRows) {
		return fallback, coverageError("statutory_gsis", run)
	}

	if err != nil {
		return fallback, fmt.Errorf("load statutory_gsis: %w", err)
	}

	return statutory.GSISConfig{
		EmployeeRate:           employeeRate,
		EmployerRate:           employerRate,
		MaxAMCLimit:            money.Centavos(maxAMC),
		AboveLimitEmployeeRate: aboveLimitRate.Float64,
	}, nil
}

func loadTaxBrackets(ctx context.Context, db *sql.DB, run Run, frequency statutory.Frequency) ([]statutory.TaxBracket, error) {
	if !tableExists(ctx, db, "statutory_tax_brackets") {
		return statutory.TRAINTaxBrackets(frequency), nil
	}

	rows, err := db.QueryContext(ctx, `
		SELECT from_centavos, to_centavos, base_tax_centavos, rate
		FROM statutory_tax_brackets
		WHERE frequency = ?
		  AND country_code = ?
		  AND (company_id IS NULL OR company_id = ?)
		  AND (effective_from IS NULL OR effective_from <= ?)
		  AND (effective_to IS NULL OR effective_to >= ?)
		ORDER BY from_centavos`, string(frequency), "PH", run.CompanyID, run.PayrollDate, run.PayrollDate)
	if err != nil {
		return nil, fmt.Errorf("load statutory_tax_brackets: %w", err)
	}

	defer rows.Close()

	brackets := []statutory.TaxBracket{}

	for rows.Next() {
		var (
			bracket statutory.TaxBracket
			to      sql.NullInt64
			base    int64
			rate    float64
		)

		if err := rows.Scan(&bracket.From, &to, &base, &rate); err != nil {
			return nil, fmt.Errorf("scan statutory_tax_brackets: %w", err)
		}

		if to.Valid {
			bracket.To = money.Centavos(to.Int64)
		}

		bracket.BaseTax = money.Centavos(base)
		bracket.Rate = rate
		brackets = append(brackets, bracket)
	}

	if len(brackets) == 0 {
		return nil, coverageError("statutory_tax_brackets", run)
	}

	return brackets, nil
}

func ensureTaxBrackets(brackets []statutory.TaxBracket, frequency statutory.Frequency) []statutory.TaxBracket {
	if len(brackets) == 0 {
		return statutory.TRAINTaxBrackets(frequency)
	}

	return brackets
}

func loadMinimumWages(ctx context.Context, db *sql.DB, run Run) []model.MinimumWage {
	wages := []model.MinimumWage{}

	if tableExists(ctx, db, "statutory_min_wage") {
		industryExpr := "''"
		wageOrderExpr := "''"
		effectiveToExpr := "NULL"

		if columnExists(ctx, db, "statutory_min_wage", "industry") {
			industryExpr = "COALESCE(industry, '')"
		}

		if columnExists(ctx, db, "statutory_min_wage", "wage_order") {
			wageOrderExpr = "COALESCE(wage_order, '')"
		}

		if columnExists(ctx, db, "statutory_min_wage", "effective_to") {
			effectiveToExpr = "COALESCE(DATE_FORMAT(effective_to, '%%Y-%%m-%%d'), '')"
		}

		query := fmt.Sprintf(`
			SELECT COALESCE(region, ''), %s, %s, amount_centavos,
			       COALESCE(DATE_FORMAT(effective_from, '%%Y-%%m-%%d'), ''),
			       %s
			FROM statutory_min_wage
			WHERE country_code = ?
			  AND (company_id IS NULL OR company_id = ?)
			  AND (effective_from IS NULL OR effective_from <= ?)
			  AND (effective_to IS NULL OR effective_to >= ?)
			ORDER BY region, industry, effective_from`, industryExpr, wageOrderExpr, effectiveToExpr)

		rows, err := db.QueryContext(ctx, query, "PH", run.CompanyID, run.PayrollDate, run.PayrollDate)
		if err == nil {
			defer rows.Close()

			for rows.Next() {
				var wage model.MinimumWage
				var amount int64

				if err := rows.Scan(&wage.Region, &wage.Industry, &wage.WageOrder, &amount, &wage.EffectiveFrom, &wage.EffectiveTo); err == nil {
					wage.Amount = money.Centavos(amount)
					wages = append(wages, wage)
				}
			}
		}
	}

	if len(wages) == 0 && tableExists(ctx, db, "minimum_wage_rates") {
		rows, err := db.QueryContext(ctx, `
			SELECT COALESCE(region, ''), COALESCE(industry, ''), '', COALESCE(wage_start, 0), '', ''
			FROM minimum_wage_rates
			WHERE deleted_at IS NULL AND company_id = ?`, run.CompanyID)
		if err == nil {
			defer rows.Close()

			for rows.Next() {
				var wage model.MinimumWage
				var amount int64

				if err := rows.Scan(&wage.Region, &wage.Industry, &wage.WageOrder, &amount, &wage.EffectiveFrom, &wage.EffectiveTo); err == nil {
					wage.Amount = money.Centavos(amount)
					wages = append(wages, wage)
				}
			}
		}
	}

	return wages
}

func resolveMinimumWage(cfg model.Config, employee model.Employee) money.Centavos {
	region := strings.ToUpper(strings.TrimSpace(employee.Region))
	industry := strings.ToUpper(strings.TrimSpace(employee.Industry))

	if region == "" {
		region = strings.ToUpper(strings.TrimSpace(cfg.DefaultRegion))
	}

	if industry == "" {
		industry = strings.ToUpper(strings.TrimSpace(cfg.DefaultIndustry))
	}

	best := money.Zero
	bestScore := -1
	bestFrom := ""

	for _, wage := range cfg.MinimumWages {
		wageRegion := strings.ToUpper(strings.TrimSpace(wage.Region))
		wageIndustry := strings.ToUpper(strings.TrimSpace(wage.Industry))

		score := -1

		switch {
		case wageRegion == region && wageIndustry == industry:
			score = 4
		case wageRegion == region && (wageIndustry == "" || wageIndustry == "GENERAL"):
			score = 3
		case wageRegion == region:
			score = 2
		case wageRegion == "" || wageIndustry == "GENERAL":
			score = 1
		}

		if score < 0 {
			continue
		}

		if score > bestScore || (score == bestScore && (wage.EffectiveFrom > bestFrom || (wage.EffectiveFrom == bestFrom && wage.Amount > best))) {
			best = wage.Amount
			bestScore = score
			bestFrom = wage.EffectiveFrom
		}
	}

	return best
}

func loadMinimumWage(ctx context.Context, db *sql.DB, run Run) money.Centavos {
	if tableExists(ctx, db, "statutory_min_wage") {
		var amount int64

		err := db.QueryRowContext(ctx, `
			SELECT amount_centavos
			FROM statutory_min_wage
			WHERE country_code = ?
			  AND (company_id IS NULL OR company_id = ?)
			  AND (effective_from IS NULL OR effective_from <= ?)
			ORDER BY effective_from DESC
			LIMIT 1`, "PH", run.CompanyID, run.PayrollDate).Scan(&amount)
		if err == nil && amount > 0 {
			return money.Centavos(amount)
		}
	}

	if tableExists(ctx, db, "minimum_wage_rates") {
		var amount int64

		err := db.QueryRowContext(ctx, `
			SELECT COALESCE(MAX(wage_start), 0)
			FROM minimum_wage_rates
			WHERE deleted_at IS NULL AND company_id = ?`, run.CompanyID).Scan(&amount)
		if err == nil && amount > 0 {
			return money.Centavos(amount)
		}
	}

	return 0
}
