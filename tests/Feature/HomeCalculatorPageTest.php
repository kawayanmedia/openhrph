<?php

it('renders both payroll calculators on the home page', function () {
    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertSee('Withholding Tax Calculator')
        ->assertSee('Take-Home Pay Calculator')
        ->assertSee('id="tax-calculator-form"', false)
        ->assertSee('id="take-home-form"', false);
});
