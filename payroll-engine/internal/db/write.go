package db

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"time"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
)

// Summary aggregates a written payroll run.
type Summary struct {
	PayrollID     int64
	EmployeeCount int
	Gross         money.Centavos
	Deductions    money.Centavos
	Net           money.Centavos
	Revision      int
	Warnings      []string
}

// WriteOptions carries the run metadata that is recorded with the payroll.
type WriteOptions struct {
	EngineVersion string
	InputHash     string
	ActorID       int64
	AllowReopen   bool
	ReopenReason  string
}

// Write snapshots the current details, replaces them with the computed
// payslips, updates the run totals and records an audit event. It is
// idempotent: re-running a payroll produces the same rows and keeps the
// previous run in payroll_detail_revisions.
func Write(ctx context.Context, db *sql.DB, run Run, slips []model.Payslip, options WriteOptions) (Summary, error) {
	summary := Summary{PayrollID: run.PayrollID, EmployeeCount: len(slips)}

	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return summary, fmt.Errorf("begin: %w", err)
	}

	defer func() { _ = tx.Rollback() }()

	status := ""
	_ = tx.QueryRowContext(ctx,
		"SELECT COALESCE(status, 'open') FROM payrolls WHERE id = ? AND company_id = ?",
		run.PayrollID, run.CompanyID).Scan(&status)

	if (status == "approved" || status == "closed") && !options.AllowReopen {
		return summary, fmt.Errorf("payroll %d is %s; reopen it with a reason before re-processing", run.PayrollID, status)
	}

	// Re-processing must not double count the year-to-date ledger, so first
	// remove what this payroll previously contributed.
	if err := reverseYTD(ctx, tx, run); err != nil {
		return summary, err
	}

	revision := 0

	if tableExists(ctx, db, "payroll_detail_revisions") {
		revision, err = snapshotRevision(ctx, tx, run, options)
		if err != nil {
			return summary, err
		}
	}

	if _, err := tx.ExecContext(ctx,
		"DELETE FROM payroll_details WHERE payroll_id = ? AND company_id = ?",
		run.PayrollID, run.CompanyID); err != nil {
		return summary, fmt.Errorf("clear details: %w", err)
	}

	now := time.Now()

	statement, err := tx.PrepareContext(ctx, insertDetailsSQL)
	if err != nil {
		return summary, fmt.Errorf("prepare insert: %w", err)
	}

	defer statement.Close()

	for _, slip := range slips {
		compensations := jsonArray(slip.Compensations)
		warnings := jsonArray(slip.Warnings)

		if _, err := statement.ExecContext(ctx,
			run.CompanyID, run.PayrollID, slip.EmployeeID, run.PayrollDate.Year(),
			int64(slip.BasicMonthly), int64(slip.PeriodBasic), int64(slip.DailyRate),
			int64(slip.Gross), int64(slip.TaxableIncome), int64(slip.NonTaxable), int64(slip.WithholdingTax),
			int64(slip.SSSEmployee), int64(slip.SSSProvident), int64(slip.SSSEmployee.Add(slip.SSSProvident)),
			int64(slip.SSSEmployer), int64(slip.SSSProvidentER), int64(slip.SSSEC),
			int64(slip.SSSEmployer.Add(slip.SSSProvidentER).Add(slip.SSSEC)), int64(slip.SSSTotal),
			int64(slip.PagIBIGEmployee), int64(slip.PagIBIGEmployer), int64(slip.PagIBIGEmployee.Add(slip.PagIBIGEmployer)),
			int64(slip.PhilHealthEmployee), int64(slip.PhilHealthEmployer), int64(slip.PhilHealthEmployee.Add(slip.PhilHealthEmployer)),
			slip.RegularHours, int64(slip.RegularPay), slip.OvertimeHours, int64(slip.OvertimePay),
			slip.NightHours, int64(slip.NightPay), int64(slip.RestDayPay), int64(slip.HolidayPay),
			slip.AbsentDays, int64(slip.AbsentAmount), int64(slip.Allowances), int64(slip.Incentives),
			compensations, int64(slip.Bonus), int64(slip.Commission),
			int64(slip.ThirteenthMonth), int64(slip.LeaveConversion), int64(slip.SeparationPay),
			int64(slip.Loans), int64(slip.BasicEarned), int64(slip.BenefitExempt),
			int64(slip.MinimumWageExemption), warnings,
			int64(slip.SSSEmployee.Add(slip.SSSProvident).Add(slip.PhilHealthEmployee).Add(slip.PagIBIGEmployee)),
			int64(slip.TotalEmployerCost), int64(slip.TotalDeductions), int64(slip.NetPay),
			now, now,
		); err != nil {
			return summary, fmt.Errorf("insert details for employee %d: %w", slip.EmployeeID, err)
		}

		summary.Gross = summary.Gross.Add(slip.Gross)
		summary.Deductions = summary.Deductions.Add(slip.TotalDeductions)
		summary.Net = summary.Net.Add(slip.NetPay)
		summary.Warnings = append(summary.Warnings, slip.Warnings...)
	}

	summary.Revision = revision

	warnings := jsonArray(summary.Warnings)

	if _, err := tx.ExecContext(ctx, `
		UPDATE payrolls
		SET gross_amount = ?, net_amount = ?, status = 'processed', updated_at = ?,
		    engine_version = COALESCE(?, engine_version),
		    processed_at = ?,
		    processed_employees = ?,
		    warnings = ?,
		    input_hash = COALESCE(?, input_hash)
		WHERE id = ? AND company_id = ?`,
		int64(summary.Gross), int64(summary.Net), time.Now(),
		nullString(options.EngineVersion), now, len(slips), warnings,
		nullString(options.InputHash), run.PayrollID, run.CompanyID,
	); err != nil {
		return summary, fmt.Errorf("update payroll: %w", err)
	}

	if err := updateYTD(ctx, tx, run, slips); err != nil {
		return summary, err
	}

	if err := recordAudit(ctx, tx, run, summary, options); err != nil {
		return summary, err
	}

	if err := tx.Commit(); err != nil {
		return summary, fmt.Errorf("commit: %w", err)
	}

	return summary, nil
}

// jsonArray marshals a slice as a JSON array, never as JSON null.
func jsonArray[T any](values []T) string {
	if len(values) == 0 {
		return "[]"
	}

	encoded, err := json.Marshal(values)
	if err != nil {
		return "[]"
	}

	return string(encoded)
}

func nullString(value string) any {
	if value == "" {
		return nil
	}

	return value
}

func snapshotRevision(ctx context.Context, tx *sql.Tx, run Run, options WriteOptions) (int, error) {
	var count int
	if err := tx.QueryRowContext(ctx,
		"SELECT COUNT(*) FROM payroll_details WHERE payroll_id = ? AND company_id = ? AND deleted_at IS NULL",
		run.PayrollID, run.CompanyID).Scan(&count); err != nil {
		return 0, fmt.Errorf("count details: %w", err)
	}

	if count == 0 {
		return 0, nil
	}

	var revision int
	if err := tx.QueryRowContext(ctx, `
		SELECT COALESCE(MAX(revision), 0) + 1
		FROM payroll_detail_revisions
		WHERE payroll_id = ? AND company_id = ?`, run.PayrollID, run.CompanyID).Scan(&revision); err != nil {
		return 0, fmt.Errorf("next revision: %w", err)
	}

	_, err := tx.ExecContext(ctx, `
		INSERT INTO payroll_detail_revisions (
			company_id, payroll_id, revision, payroll_status, engine_version, employee_id,
			basic_monthly_salary, basic_salary, daily_rate, gross_income, taxable_income, non_taxable_income,
			withholding_tax, sss_employee, sss_provident_employee, sss_employer, sss_provident_employer,
			sss_employer_ec_er, hdmf_employee, hdmf_employer, philhealth_employee, philhealth_employer,
			regular_pay, overtime_pay, night_pay, rest_day_pay, holiday_pay, allowance, incentive,
			total_employee, total_employer, total_deductions, net_pay, warnings, created_at
		)
		SELECT company_id, payroll_id, ?, ?, ?, employee_id,
		       basic_monthly_salary, basic_salary, daily_rate, gross_income, taxable_income, non_taxable_income,
		       withholding_tax, sss_employee, sss_provident_employee, sss_employer, sss_provident_employer,
		       sss_employer_ec_er, hdmf_employee, hdmf_employer, philhealth_employee, philhealth_employer,
		       regular_pay, overtime_pay, night_pay, rest_day_pay, holiday_pay, allowance, incentive,
		       total_employee, total_employer, total_deductions, net_pay, warnings, ?
		FROM payroll_details
		WHERE payroll_id = ? AND company_id = ? AND deleted_at IS NULL`,
		revision, run.Status, nullString(options.EngineVersion), time.Now(), run.PayrollID, run.CompanyID)
	if err != nil {
		return 0, fmt.Errorf("snapshot revision: %w", err)
	}

	return revision, nil
}

// reverseYTD removes the contribution of the payroll's current details from
// the year-to-date ledger so a re-processing replaces rather than adds.
func reverseYTD(ctx context.Context, tx *sql.Tx, run Run) error {
	if run.Type == model.RunAnnualAdjustment {
		// Annual adjustments do not accrue to the ledger.
		return nil
	}

	var exists int
	if err := tx.QueryRowContext(ctx, `
		SELECT COUNT(*) FROM information_schema.tables
		WHERE table_schema = DATABASE() AND table_name = 'employee_payroll_ytd'`).Scan(&exists); err != nil || exists == 0 {
		return nil
	}

	var hasColumns int
	if err := tx.QueryRowContext(ctx, `
		SELECT COUNT(*) FROM information_schema.columns
		WHERE table_schema = DATABASE() AND table_name = 'payroll_details'
		  AND column_name IN ('basic_earned', 'benefit_exempt')`).Scan(&hasColumns); err != nil || hasColumns < 2 {
		return nil
	}

	rows, err := tx.QueryContext(ctx, `
		SELECT employee_id,
		       COALESCE(SUM(basic_earned), 0),
		       COALESCE(SUM(gross_income), 0),
		       COALESCE(SUM(taxable_income), 0),
		       COALESCE(SUM(withholding_tax), 0),
		       COALESCE(SUM(sss_employee_total + hdmf_employee + philhealth_employee), 0),
		       COALESCE(SUM(thirteenth_month), 0),
		       COALESCE(SUM(benefit_exempt), 0)
		FROM payroll_details
		WHERE payroll_id = ? AND company_id = ? AND deleted_at IS NULL
		GROUP BY employee_id`, run.PayrollID, run.CompanyID)
	if err != nil {
		return fmt.Errorf("load previous ytd contribution: %w", err)
	}

	defer rows.Close()

	type ytdDelta struct {
		employeeID    int64
		basic, gross  int64
		taxable, tax  int64
		contributions int64
		thirteenth    int64
		otherBenefits int64
	}

	deltas := []ytdDelta{}

	for rows.Next() {
		var delta ytdDelta
		if err := rows.Scan(&delta.employeeID, &delta.basic, &delta.gross, &delta.taxable,
			&delta.tax, &delta.contributions, &delta.thirteenth, &delta.otherBenefits); err != nil {
			return fmt.Errorf("scan previous ytd contribution: %w", err)
		}

		deltas = append(deltas, delta)
	}

	if err := rows.Err(); err != nil {
		return err
	}

	for _, delta := range deltas {
		if _, err := tx.ExecContext(ctx, `
			UPDATE employee_payroll_ytd
			SET basic_earned = GREATEST(0, basic_earned - ?),
			    gross_earned = GREATEST(0, gross_earned - ?),
			    taxable_income = GREATEST(0, taxable_income - ?),
			    tax_withheld = GREATEST(0, tax_withheld - ?),
			    contributions = GREATEST(0, contributions - ?),
			    thirteenth_month_paid = GREATEST(0, thirteenth_month_paid - ?),
			    other_benefits = GREATEST(0, other_benefits - ?)
			WHERE company_id = ? AND employee_id = ? AND year = ?`,
			delta.basic, delta.gross, delta.taxable, delta.tax, delta.contributions,
			delta.thirteenth, delta.otherBenefits, run.CompanyID, delta.employeeID, run.PayrollDate.Year(),
		); err != nil {
			return fmt.Errorf("reverse ytd for employee %d: %w", delta.employeeID, err)
		}
	}

	return nil
}

func updateYTD(ctx context.Context, tx *sql.Tx, run Run, slips []model.Payslip) error {
	var exists int
	if err := tx.QueryRowContext(ctx, `
		SELECT COUNT(*) FROM information_schema.tables
		WHERE table_schema = DATABASE() AND table_name = 'employee_payroll_ytd'`).Scan(&exists); err != nil || exists == 0 {
		return nil
	}

	now := time.Now()

	for _, slip := range slips {
		if slip.Gross == 0 {
			continue
		}

		basicEarned := int64(slip.BasicEarned)
		otherBenefits := int64(slip.BenefitExempt)
		thirteenth := int64(0)

		switch run.Type {
		case model.RunThirteenthMonth:
			basicEarned = 0
			otherBenefits = 0
			thirteenth = int64(slip.ThirteenthMonth)
		case model.RunFinalPay:
			thirteenth = int64(slip.ThirteenthMonth)
			otherBenefits = 0
		}

		contributions := int64(slip.SSSEmployee.Add(slip.SSSProvident).
			Add(slip.PhilHealthEmployee).
			Add(slip.PagIBIGEmployee))

		if _, err := tx.ExecContext(ctx, `
			INSERT INTO employee_payroll_ytd (
				company_id, employee_id, year, basic_earned, gross_earned, taxable_income,
				tax_withheld, contributions, thirteenth_month_paid, other_benefits, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				basic_earned = basic_earned + VALUES(basic_earned),
				gross_earned = gross_earned + VALUES(gross_earned),
				taxable_income = taxable_income + VALUES(taxable_income),
				tax_withheld = tax_withheld + VALUES(tax_withheld),
				contributions = contributions + VALUES(contributions),
				thirteenth_month_paid = thirteenth_month_paid + VALUES(thirteenth_month_paid),
				other_benefits = other_benefits + VALUES(other_benefits),
				updated_at = VALUES(updated_at)`,
			run.CompanyID, slip.EmployeeID, run.PayrollDate.Year(),
			basicEarned, int64(slip.Gross), int64(slip.TaxableIncome), int64(slip.WithholdingTax),
			contributions, thirteenth, otherBenefits, now, now,
		); err != nil {
			return fmt.Errorf("update ytd for employee %d: %w", slip.EmployeeID, err)
		}
	}

	return nil
}

func recordAudit(ctx context.Context, tx *sql.Tx, run Run, summary Summary, options WriteOptions) error {
	var exists int
	if err := tx.QueryRowContext(ctx, `
		SELECT COUNT(*) FROM information_schema.tables
		WHERE table_schema = DATABASE() AND table_name = 'payroll_audit_events'`).Scan(&exists); err != nil || exists == 0 {
		return nil
	}

	payload, _ := json.Marshal(map[string]any{
		"employees": summary.EmployeeCount,
		"gross":     summary.Gross.String(),
		"net":       summary.Net.String(),
		"revision":  summary.Revision,
		"warnings":  len(summary.Warnings),
	})

	event := "processed"
	reason := sql.NullString{}
	if summary.Revision > 0 || run.Status == "approved" || run.Status == "closed" {
		event = "reprocessed"
	}

	if options.AllowReopen {
		reason = sql.NullString{String: options.ReopenReason, Valid: options.ReopenReason != ""}
	}

	if _, err := tx.ExecContext(ctx, `
		INSERT INTO payroll_audit_events (
			company_id, payroll_id, event, actor_id, reason, engine_version,
			gross_amount, net_amount, payload, created_at
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		run.CompanyID, run.PayrollID, event, nullInt(options.ActorID), reason,
		nullString(options.EngineVersion), int64(summary.Gross), int64(summary.Net),
		string(payload), time.Now(),
	); err != nil {
		return fmt.Errorf("record audit event: %w", err)
	}

	return nil
}

func nullInt(value int64) any {
	if value == 0 {
		return nil
	}

	return value
}

const insertDetailsSQL = `
INSERT INTO payroll_details (
	company_id, payroll_id, employee_id, year,
	basic_monthly_salary, basic_salary, daily_rate,
	gross_income, taxable_income, non_taxable_income, withholding_tax,
	sss_employee, sss_provident_employee, sss_employee_total,
	sss_employer, sss_provident_employer, sss_employer_ec_er, sss_employer_total, sss_total,
	hdmf_employee, hdmf_employer, hdmf_total,
	philhealth_employee, philhealth_employer, total_philhealth,
	regular_hours, regular_pay, overtime_hours, overtime_pay,
	night_hours, night_pay, rest_day_pay, holiday_pay,
	absent_days, absent_days_amount, allowance, incentive,
	compensations, bonus, commission, thirteenth_month, leave_conversion, separation_pay,
	loans, basic_earned, benefit_exempt, minimum_wage_exemption, warnings,
	total_employee, total_employer, total_deductions, net_pay,
	created_at, updated_at
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`
