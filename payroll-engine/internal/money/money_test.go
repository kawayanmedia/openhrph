package money

import "testing"

func TestFromPesosRoundsHalfUp(t *testing.T) {
	cases := []struct {
		pesos float64
		want  Centavos
	}{
		{0, 0},
		{1234.56, 123456},
		{0.005, 1},
		{0.004, 0},
		{10000, 1000000},
		{-12.345, -1235},
	}

	for _, tc := range cases {
		if got := FromPesos(tc.pesos); got != tc.want {
			t.Fatalf("FromPesos(%v) = %d, want %d", tc.pesos, got, tc.want)
		}
	}
}

func TestFromPesosString(t *testing.T) {
	cases := []struct {
		in   string
		want Centavos
	}{
		{"", 0},
		{"1200", 120000},
		{"1,234.5", 123450},
		{"abc", 0},
		{" 99.99 ", 9999},
	}

	for _, tc := range cases {
		if got := FromPesosString(tc.in); got != tc.want {
			t.Fatalf("FromPesosString(%q) = %d, want %d", tc.in, got, tc.want)
		}
	}
}

func TestString(t *testing.T) {
	cases := []struct {
		in   Centavos
		want string
	}{
		{0, "0.00"},
		{5, "0.05"},
		{123456, "1234.56"},
		{-123456, "-1234.56"},
	}

	for _, tc := range cases {
		if got := tc.in.String(); got != tc.want {
			t.Fatalf("String() = %q, want %q", got, tc.want)
		}
	}
}

func TestSplitKeepsTotal(t *testing.T) {
	parts := Centavos(1001).Split(2)
	if parts[0]+parts[1] != 1001 {
		t.Fatalf("split lost money: %v", parts)
	}

	if parts[0] != 501 || parts[1] != 500 {
		t.Fatalf("unexpected split: %v", parts)
	}

	if got := Centavos(301).Half(); got != 151 {
		t.Fatalf("Half() = %d, want 151", got)
	}
}

func TestClampTo(t *testing.T) {
	if got := Centavos(500).ClampTo(1000, 2000); got != 1000 {
		t.Fatalf("clamp low = %d", got)
	}

	if got := Centavos(5000).ClampTo(1000, 2000); got != 2000 {
		t.Fatalf("clamp high = %d", got)
	}

	if got := Centavos(1500).ClampTo(1000, 2000); got != 1500 {
		t.Fatalf("clamp passthrough = %d", got)
	}
}
