package db

import "testing"

func TestWorkedHoursFromLogs(t *testing.T) {
	cases := []struct {
		name string
		raw  string
		want float64
	}{
		{
			name: "day shift with a break",
			raw:  `[{"time":"08:00:00","log_type":0},{"time":"12:00:00","log_type":1},{"time":"13:00:00","log_type":0},{"time":"17:00:00","log_type":1}]`,
			want: 8,
		},
		{
			name: "overnight shift",
			raw:  `[{"time":"22:00:00","log_type":0},{"time":"06:00:00","log_type":1}]`,
			want: 8,
		},
		{
			name: "string log type",
			raw:  `[{"time":"10:00","log_type":"0"},{"time":"15:30","log_type":"1"}]`,
			want: 5.5,
		},
		{
			name: "missing time out",
			raw:  `[{"time":"08:00:00","log_type":0}]`,
			want: 0,
		},
		{
			name: "missing time in",
			raw:  `[{"time":"17:00:00","log_type":1}]`,
			want: 0,
		},
		{
			name: "invalid time",
			raw:  `[{"time":"25:00","log_type":0},{"time":"17:00:00","log_type":1}]`,
			want: 0,
		},
		{name: "empty", raw: "", want: 0},
		{name: "malformed json", raw: "{", want: 0},
	}

	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if got := workedHoursFromLogs(c.raw); got != c.want {
				t.Fatalf("workedHoursFromLogs() = %v, want %v", got, c.want)
			}
		})
	}
}

func TestWorkedHoursFromColumns(t *testing.T) {
	if got := workedHoursFromColumns("08:00", "12:00", "13:00", "17:00", ""); got != 8 {
		t.Fatalf("day shift = %v, want 8", got)
	}

	if got := workedHoursFromColumns("", "", "22:00", "", "06:00"); got != 8 {
		t.Fatalf("overnight = %v, want 8", got)
	}

	if got := workedHoursFromColumns("08:00", "", "13:00", "17:00", ""); got != 4 {
		t.Fatalf("missing morning out = %v, want 4", got)
	}

	if got := workedHoursFromColumns("", "", "", "", ""); got != 0 {
		t.Fatalf("empty columns = %v, want 0", got)
	}
}
