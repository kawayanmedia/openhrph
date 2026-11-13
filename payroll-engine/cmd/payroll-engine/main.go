// Command payroll-engine computes a Philippine payroll run.
//
// It is designed to be invoked by the Laravel application as a subprocess:
//
//	payroll-engine --payroll-id=24 [--dry-run] [--force]
//
// The engine reads MySQL directly (payroll, employees, attendance, statutory
// tables), computes every payslip and – unless --dry-run is given – replaces the
// payroll's details and totals inside a single transaction. The JSON summary on
// stdout is the contract consumed by the application.
package main

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"os"
	"strings"
	"time"

	"github.com/kawayanmedia/openhrph-payroll-engine/internal/db"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/model"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/money"
	"github.com/kawayanmedia/openhrph-payroll-engine/internal/payroll"
)

// version is injected at build time.
var version = "dev"

var engineVersion = "1.0.0"

type employeeOutput struct {
	EmployeeID     int64    `json:"employee_id"`
	EmployeeNo     string   `json:"employee_number"`
	Name           string   `json:"name"`
	Gross          string   `json:"gross"`
	TaxableIncome  string   `json:"taxable_income"`
	WithholdingTax string   `json:"withholding_tax"`
	SSSEmployee    string   `json:"sss_employee"`
	PhilHealth     string   `json:"philhealth_employee"`
	PagIBIG        string   `json:"pagibig_employee"`
	Deductions     string   `json:"total_deductions"`
	NetPay         string   `json:"net_pay"`
	EmployerCost   string   `json:"employer_cost"`
	Warnings       []string `json:"warnings,omitempty"`
}

type output struct {
	EngineVersion string           `json:"engine_version"`
	PayrollID     int64            `json:"payroll_id"`
	DryRun        bool             `json:"dry_run"`
	Employees     int              `json:"employees"`
	Gross         string           `json:"gross"`
	Deductions    string           `json:"deductions"`
	Net           string           `json:"net"`
	DurationMS    int64            `json:"duration_ms"`
	Revision      int              `json:"revision"`
	Warnings      []string         `json:"warnings"`
	Payslips      []employeeOutput `json:"payslips"`
}

func main() {
	if err := run(); err != nil {
		fmt.Fprintln(os.Stderr, "payroll-engine:", err)
		os.Exit(1)
	}
}

func run() error {
	var (
		payrollID = flag.Int64("payroll-id", 0, "payroll id to process")
		dsn       = flag.String("dsn", "", "MySQL DSN (defaults to DB_* environment variables)")
		dryRun    = flag.Bool("dry-run", false, "compute and report without writing")
		force     = flag.Bool("force", false, "deprecated: use --reopen with --reason")
		reopen    = flag.Bool("reopen", false, "reopen an approved or closed payroll and record why")
		reason    = flag.String("reason", "", "reason for reopening (required with --reopen)")
		actor     = flag.Int64("actor", 0, "user id recorded on the audit event")
		showVer   = flag.Bool("version", false, "print the engine version and exit")
		_         = flag.Bool("json", true, "emit a JSON summary (always on)")
		timeout   = flag.Duration("timeout", 10*time.Minute, "maximum run duration")
	)

	flag.Parse()

	if *showVer {
		fmt.Printf("payroll-engine %s (engine %s)\n", version, engineVersion)

		return nil
	}

	if *payrollID <= 0 {
		return errors.New("--payroll-id is required")
	}

	started := time.Now()

	ctx, cancel := context.WithTimeout(context.Background(), *timeout)
	defer cancel()

	connection, err := db.Open(*dsn)
	if err != nil {
		return err
	}

	defer connection.Close()

	input, err := db.Load(ctx, connection, *payrollID)
	if err != nil {
		if errors.Is(err, db.ErrNoPayroll) {
			return fmt.Errorf("payroll %d not found", *payrollID)
		}

		return err
	}

	status := strings.ToLower(input.Run.Status)
	if !*dryRun && (status == "approved" || status == "closed") {
		if !*reopen && !*force {
			return fmt.Errorf("payroll %d is %s; reopen it with --reopen --reason=<why> to re-process", *payrollID, status)
		}

		if strings.TrimSpace(*reason) == "" {
			return fmt.Errorf("payroll %d is %s; --reason is required to reopen it", *payrollID, status)
		}
	}

	slips, err := payroll.ComputeAll(input.Config, input.Employees)
	if err != nil {
		return err
	}

	result := output{
		EngineVersion: engineVersion,
		PayrollID:     *payrollID,
		DryRun:        *dryRun,
		Employees:     len(slips),
		Warnings:      []string{},
		Payslips:      make([]employeeOutput, 0, len(slips)),
	}

	totalGross := money.Zero
	totalDeductions := money.Zero
	totalNet := money.Zero

	for _, slip := range slips {
		result.Payslips = append(result.Payslips, employeeOutput{
			EmployeeID:     slip.EmployeeID,
			EmployeeNo:     slip.EmployeeNo,
			Name:           slip.EmployeeName,
			Gross:          slip.Gross.String(),
			TaxableIncome:  slip.TaxableIncome.String(),
			WithholdingTax: slip.WithholdingTax.String(),
			SSSEmployee:    slip.SSSEmployee.Add(slip.SSSProvident).String(),
			PhilHealth:     slip.PhilHealthEmployee.String(),
			PagIBIG:        slip.PagIBIGEmployee.String(),
			Deductions:     slip.TotalDeductions.String(),
			NetPay:         slip.NetPay.String(),
			EmployerCost:   slip.TotalEmployerCost.String(),
			Warnings:       slip.Warnings,
		})

		totalGross = totalGross.Add(slip.Gross)
		totalDeductions = totalDeductions.Add(slip.TotalDeductions)
		totalNet = totalNet.Add(slip.NetPay)
		result.Warnings = append(result.Warnings, slip.Warnings...)
	}

	if !*dryRun {
		release, err := db.Lock(ctx, connection, *payrollID)
		if err != nil {
			return err
		}

		defer release()

		// Reload under the lock so a concurrent run cannot change the inputs.
		input, err = db.Load(ctx, connection, *payrollID)
		if err != nil {
			return err
		}

		slips, err = payroll.ComputeAll(input.Config, input.Employees)
		if err != nil {
			return err
		}

		summary, err := db.Write(ctx, connection, input.Run, slips, db.WriteOptions{
			EngineVersion: engineVersion,
			InputHash:     inputHash(input),
			ActorID:       *actor,
			AllowReopen:   *reopen || *force,
			ReopenReason:  *reason,
		})
		if err != nil {
			return err
		}

		totalGross = summary.Gross
		totalDeductions = summary.Deductions
		totalNet = summary.Net
		result.Revision = summary.Revision
	}

	result.Gross = totalGross.String()
	result.Deductions = totalDeductions.String()
	result.Net = totalNet.String()
	result.DurationMS = time.Since(started).Milliseconds()

	encoded, err := json.Marshal(result)
	if err != nil {
		return err
	}

	fmt.Println(string(encoded))

	return nil
}

// inputHash fingerprints the inputs of a run so a re-processing can be
// compared with the run it replaces.
func inputHash(input db.Input) string {
	payload, err := json.Marshal(struct {
		PayrollID int64
		CompanyID int64
		GroupID   int64
		Type      string
		DateFrom  time.Time
		DateTo    time.Time
		Employees []model.Employee
	}{
		PayrollID: input.Run.PayrollID,
		CompanyID: input.Run.CompanyID,
		GroupID:   input.Run.GroupID,
		Type:      input.Run.Type,
		DateFrom:  input.Run.DateFrom,
		DateTo:    input.Run.DateTo,
		Employees: input.Employees,
	})
	if err != nil {
		return ""
	}

	sum := sha256.Sum256(payload)

	return hex.EncodeToString(sum[:])
}
