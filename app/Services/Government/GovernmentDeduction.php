<?php

namespace App\Services\Government;

use App\Support\Tenancy\TenantCache;
use Illuminate\Support\Facades\Cache;

trait GovernmentDeduction
{
    // https://mpm.ph/2023-sss-table/
    public function getSSS2023($basicSalary)
    {
        $sss2023 = [
            ['id' => '1', 'from' => '0', 'to' => '4249.99', 'regular_ec' => '4000.00', 'wisp' => '0', 'regular_er' => '380', 'regular_ee' => '180.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '2', 'from' => '4250.00', 'to' => '4749.99', 'regular_ec' => '4500.00', 'wisp' => '0', 'regular_er' => '427.5', 'regular_ee' => '202.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '3', 'from' => '4750.00', 'to' => '5249.99', 'regular_ec' => '5000.00', 'wisp' => '0', 'regular_er' => '475', 'regular_ee' => '225.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '4', 'from' => '5250.00', 'to' => '5749.99', 'regular_ec' => '5500.00', 'wisp' => '0', 'regular_er' => '522.5', 'regular_ee' => '247.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '5', 'from' => '5750.00', 'to' => '6249.99', 'regular_ec' => '6000.00', 'wisp' => '0', 'regular_er' => '570', 'regular_ee' => '270.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '6', 'from' => '6250.00', 'to' => '6749.99', 'regular_ec' => '6500.00', 'wisp' => '0', 'regular_er' => '617.5', 'regular_ee' => '292.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '7', 'from' => '6750.00', 'to' => '7249.99', 'regular_ec' => '7000.00', 'wisp' => '0', 'regular_er' => '665', 'regular_ee' => '315.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '8', 'from' => '7250.00', 'to' => '7749.99', 'regular_ec' => '7500.00', 'wisp' => '0', 'regular_er' => '712.5', 'regular_ee' => '337.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '9', 'from' => '7750.00', 'to' => '8249.99', 'regular_ec' => '8000.00', 'wisp' => '0', 'regular_er' => '760', 'regular_ee' => '360.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '10', 'from' => '8250.00', 'to' => '8749.99', 'regular_ec' => '8500.00', 'wisp' => '0', 'regular_er' => '807.5', 'regular_ee' => '382.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '11', 'from' => '8750.00', 'to' => '9249.99', 'regular_ec' => '9000.00', 'wisp' => '0', 'regular_er' => '855', 'regular_ee' => '405.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '12', 'from' => '9250.00', 'to' => '9749.99', 'regular_ec' => '9500.00', 'wisp' => '0', 'regular_er' => '902.5', 'regular_ee' => '427.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '13', 'from' => '9750.00', 'to' => '10249.99', 'regular_ec' => '10000.00', 'wisp' => '0', 'regular_er' => '950', 'regular_ee' => '450.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '14', 'from' => '10250.00', 'to' => '10749.99', 'regular_ec' => '10500.00', 'wisp' => '0', 'regular_er' => '997.5', 'regular_ee' => '472.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '15', 'from' => '10750.00', 'to' => '11249.99', 'regular_ec' => '11000.00', 'wisp' => '0', 'regular_er' => '1045.00', 'regular_ee' => '495.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '16', 'from' => '11250.00', 'to' => '11749.99', 'regular_ec' => '11500.00', 'wisp' => '0', 'regular_er' => '1092.50', 'regular_ee' => '517.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '17', 'from' => '11750.00', 'to' => '12249.99', 'regular_ec' => '12000.00', 'wisp' => '0', 'regular_er' => '1140.00', 'regular_ee' => '540.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '18', 'from' => '12250.00', 'to' => '12749.99', 'regular_ec' => '12500.00', 'wisp' => '0', 'regular_er' => '1187.50', 'regular_ee' => '562.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '19', 'from' => '12750.00', 'to' => '13249.99', 'regular_ec' => '13000.00', 'wisp' => '0', 'regular_er' => '1235.00', 'regular_ee' => '585.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '20', 'from' => '13250.00', 'to' => '13749.99', 'regular_ec' => '13500.00', 'wisp' => '0', 'regular_er' => '1282.50', 'regular_ee' => '607.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '21', 'from' => '13750.00', 'to' => '14249.99', 'regular_ec' => '14000.00', 'wisp' => '0', 'regular_er' => '1330.00', 'regular_ee' => '630.0', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '22', 'from' => '14250.00', 'to' => '14749.99', 'regular_ec' => '14500.00', 'wisp' => '0', 'regular_er' => '1377.50', 'regular_ee' => '652.5', 'ec_er' => '10', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '23', 'from' => '14750.00', 'to' => '15249.99', 'regular_ec' => '15000.00', 'wisp' => '0', 'regular_er' => '1425.00', 'regular_ee' => '675.0', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '24', 'from' => '15250.00', 'to' => '15749.99', 'regular_ec' => '15500.00', 'wisp' => '0', 'regular_er' => '1472.50', 'regular_ee' => '697.5', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '25', 'from' => '15750.00', 'to' => '16249.99', 'regular_ec' => '16000.00', 'wisp' => '0', 'regular_er' => '1520.00', 'regular_ee' => '720.0', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '26', 'from' => '16250.00', 'to' => '16749.99', 'regular_ec' => '16500.00', 'wisp' => '0', 'regular_er' => '1567.50', 'regular_ee' => '742.5', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '27', 'from' => '16750.00', 'to' => '17249.99', 'regular_ec' => '17000.00', 'wisp' => '0', 'regular_er' => '1615.00', 'regular_ee' => '765.0', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '28', 'from' => '17250.00', 'to' => '17749.99', 'regular_ec' => '17500.00', 'wisp' => '0', 'regular_er' => '1662.50', 'regular_ee' => '787.5', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '29', 'from' => '17750.00', 'to' => '18249.99', 'regular_ec' => '18000.00', 'wisp' => '0', 'regular_er' => '1710.00', 'regular_ee' => '810.0', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '30', 'from' => '18250.00', 'to' => '18749.99', 'regular_ec' => '18500.00', 'wisp' => '0', 'regular_er' => '1757.50', 'regular_ee' => '832.5', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '31', 'from' => '18750.00', 'to' => '19249.99', 'regular_ec' => '19000.00', 'wisp' => '0', 'regular_er' => '1805.00', 'regular_ee' => '855.0', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '32', 'from' => '19250.00', 'to' => '19749.99', 'regular_ec' => '19500.00', 'wisp' => '0', 'regular_er' => '1852.50', 'regular_ee' => '877.5', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '33', 'from' => '19750.00', 'to' => '20249.99', 'regular_ec' => '20000.00', 'wisp' => '0', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '0.0', 'wisp_ee' => '0.0'],
            ['id' => '34', 'from' => '20250.00', 'to' => '20749.99', 'regular_ec' => '20000.00', 'wisp' => '500', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '47.5', 'wisp_ee' => '22.5'],
            ['id' => '35', 'from' => '20750.00', 'to' => '21249.99', 'regular_ec' => '20000.00', 'wisp' => '1000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '95.0', 'wisp_ee' => '45.0'],
            ['id' => '36', 'from' => '21250.00', 'to' => '21749.99', 'regular_ec' => '20000.00', 'wisp' => '1500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '142.5', 'wisp_ee' => '67.5'],
            ['id' => '37', 'from' => '21750.00', 'to' => '22249.99', 'regular_ec' => '20000.00', 'wisp' => '2000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '190.0', 'wisp_ee' => '90.0'],
            ['id' => '38', 'from' => '22250.00', 'to' => '22749.99', 'regular_ec' => '20000.00', 'wisp' => '2500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '237.5', 'wisp_ee' => '112.5'],
            ['id' => '39', 'from' => '22750.00', 'to' => '23249.99', 'regular_ec' => '20000.00', 'wisp' => '3000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '285.0', 'wisp_ee' => '135.0'],
            ['id' => '40', 'from' => '23250.00', 'to' => '23749.99', 'regular_ec' => '20000.00', 'wisp' => '3500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '332.5', 'wisp_ee' => '157.5'],
            ['id' => '41', 'from' => '23750.00', 'to' => '24249.99', 'regular_ec' => '20000.00', 'wisp' => '4000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '380.0', 'wisp_ee' => '180.0'],
            ['id' => '42', 'from' => '24250.00', 'to' => '24749.99', 'regular_ec' => '20000.00', 'wisp' => '4500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '427.5', 'wisp_ee' => '202.5'],
            ['id' => '43', 'from' => '24750.00', 'to' => '25249.99', 'regular_ec' => '20000.00', 'wisp' => '5000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '475.0', 'wisp_ee' => '225.0'],
            ['id' => '44', 'from' => '25250.00', 'to' => '25749.99', 'regular_ec' => '20000.00', 'wisp' => '5500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '522.5', 'wisp_ee' => '247.5'],
            ['id' => '45', 'from' => '25750.00', 'to' => '26249.99', 'regular_ec' => '20000.00', 'wisp' => '6000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '570.0', 'wisp_ee' => '270.0'],
            ['id' => '46', 'from' => '26250.00', 'to' => '26749.99', 'regular_ec' => '20000.00', 'wisp' => '6500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '617.5', 'wisp_ee' => '292.5'],
            ['id' => '47', 'from' => '26750.00', 'to' => '27249.99', 'regular_ec' => '20000.00', 'wisp' => '7000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '665.0', 'wisp_ee' => '315.0'],
            ['id' => '48', 'from' => '27250.00', 'to' => '27749.99', 'regular_ec' => '20000.00', 'wisp' => '7500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '712.5', 'wisp_ee' => '337.5'],
            ['id' => '49', 'from' => '27750.00', 'to' => '28249.99', 'regular_ec' => '20000.00', 'wisp' => '8000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '760.0', 'wisp_ee' => '360.0'],
            ['id' => '50', 'from' => '28250.00', 'to' => '28749.99', 'regular_ec' => '20000.00', 'wisp' => '8500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '807.5', 'wisp_ee' => '382.5'],
            ['id' => '51', 'from' => '28750.00', 'to' => '29249.99', 'regular_ec' => '20000.00', 'wisp' => '9000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '855.0', 'wisp_ee' => '405.0'],
            ['id' => '52', 'from' => '29250.00', 'to' => '29749.99', 'regular_ec' => '20000.00', 'wisp' => '9500.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '902.5', 'wisp_ee' => '427.5'],
            ['id' => '53', 'from' => '29750.00', 'to' => '999999999', 'regular_ec' => '20000.00', 'wisp' => '10000.00', 'regular_er' => '1900.00', 'regular_ee' => '900.0', 'ec_er' => '30', 'wisp_er' => '950.0', 'wisp_ee' => '450.0'],
        ];

        $collection = collect($sss2023);

        $filtered = $collection->where('to', '>=', $basicSalary)->first();

        return $filtered;
    }

    // https://www.bir.gov.ph/index.php/tax-information/withholding-tax.html#wt10
    public function taxTable2023Up()
    {
        return Cache::get(TenantCache::key('tax_table_2023'), function () {
            return [
                'daily' => [
                    [
                        'number' => 1,
                        'from' => 685,
                        'to' => null,
                        'prescribed' => 0,
                        'over' => 0,
                    ],
                    [
                        'number' => 2,
                        'from' => 685,
                        'to' => 1095,
                        'prescribed' => 0,
                        'over' => 15,
                    ],
                    [
                        'number' => 3,
                        'from' => 1096,
                        'to' => 2191,
                        'prescribed' => 61.65,
                        'over' => 20,
                    ],
                    [
                        'number' => 4,
                        'from' => 2192,
                        'to' => 5478,
                        'prescribed' => 280.85,
                        'over' => 25,
                    ],
                    [
                        'number' => 5,
                        'from' => 5479,
                        'to' => 21917,
                        'prescribed' => 1102.60,
                        'over' => 30,
                    ],
                    [
                        'number' => 6,
                        'from' => 21918,
                        'to' => null,
                        'prescribed' => 6034.30,
                        'over' => 35,
                    ],
                ],
                'weekly' => [
                    [
                        'number' => 1,
                        'from' => 4808,
                        'to' => null,
                        'prescribed' => 0,
                        'over' => 0,
                    ],
                    [
                        'number' => 2,
                        'from' => 4808,
                        'to' => 7691,
                        'prescribed' => 0,
                        'over' => 15,
                    ],
                    [
                        'number' => 3,
                        'from' => 7692,
                        'to' => 15384,
                        'prescribed' => 432.60,
                        'over' => 20,
                    ],
                    [
                        'number' => 4,
                        'from' => 15385,
                        'to' => 38461,
                        'prescribed' => 1971.20,
                        'over' => 25,
                    ],
                    [
                        'number' => 5,
                        'from' => 38462,
                        'to' => 153845,
                        'prescribed' => 7740.45,
                        'over' => 30,
                    ],
                    [
                        'number' => 6,
                        'from' => 153846,
                        'to' => 99999999999999999,
                        'prescribed' => 42355.65,
                        'over' => 35,
                    ],
                ],
                'semi-monthly' => [
                    [
                        'number' => 1,
                        'from' => 10417,
                        'to' => null,
                        'prescribed' => 0,
                        'over' => 0,
                    ],
                    [
                        'number' => 2,
                        'from' => 10417,
                        'to' => 16666,
                        'prescribed' => 0,
                        'over' => 15,
                    ],
                    [
                        'number' => 3,
                        'from' => 16667,
                        'to' => 33332,
                        'prescribed' => 937.50,
                        'over' => 20,
                    ],
                    [
                        'number' => 4,
                        'from' => 33333,
                        'to' => 83332,
                        'prescribed' => 4270.70,
                        'over' => 25,
                    ],
                    [
                        'number' => 5,
                        'from' => 83333,
                        'to' => 333332,
                        'prescribed' => 16770.70,
                        'over' => 30,
                    ],
                    [
                        'number' => 6,
                        'from' => 333333,
                        'to' => 99999999999999999,
                        'prescribed' => 91770.70,
                        'over' => 35,
                    ],
                ],
                'monthly' => [
                    [
                        'number' => 1,
                        'from' => 20833,
                        'to' => null,
                        'prescribed' => 0,
                        'over' => 0,
                    ],
                    [
                        'number' => 2,
                        'from' => 20833,
                        'to' => 33332,
                        'prescribed' => 0,
                        'over' => 15,
                    ],
                    [
                        'number' => 3,
                        'from' => 333333,
                        'to' => 66666,
                        'prescribed' => 1875,
                        'over' => 20,
                    ],
                    [
                        'number' => 4,
                        'from' => 66667,
                        'to' => 166666,
                        'prescribed' => 8541.80,
                        'over' => 25,
                    ],
                    [
                        'number' => 5,
                        'from' => 166667,
                        'to' => 666666,
                        'prescribed' => 33541.80,
                        'over' => 30,
                    ],
                    [
                        'number' => 6,
                        'from' => 666667,
                        'to' => 99999999999999999,
                        'prescribed' => 183541.80,
                        'over' => 35,
                    ],
                ],
            ];
        });
    }

    public function taxTable2022()
    {
        return Cache::get(TenantCache::key('tax_table_2022'), function () {
            return [
                'daily' => [
                    [
                        'from' => 685,
                        'to' => null,
                        'prescribed' => 0,
                        'over' => 0,
                    ],
                    [
                        'from' => 685,
                        'to' => 1095,
                        'prescribed' => 0,
                        'over' => 20,
                    ],
                    [
                        'from' => 1096,
                        'to' => 2191,
                        'prescribed' => 82.19,
                        'over' => 25,
                    ],
                    [
                        'from' => 2192,
                        'to' => 5478,
                        'prescribed' => 356.16,
                        'over' => 30,
                    ],
                    [
                        'from' => 5479,
                        'to' => 21917,
                        'prescribed' => 1342.47,
                        'over' => 32,
                    ],
                    [
                        'from' => 21918,
                        'to' => null,
                        'prescribed' => 6602.74,
                        'over' => 35,
                    ],
                ],
                'weekly' => [
                    [
                        'from' => 4808,
                        'to' => null,
                        'prescribed' => 0,
                        'over' => 0,
                    ],
                    [
                        'from' => 4808,
                        'to' => 7691,
                        'prescribed' => 0,
                        'over' => 20,
                    ],
                    [
                        'from' => 7692,
                        'to' => 1584,
                        'prescribed' => 567.92,
                        'over' => 25,
                    ],
                    [
                        'from' => 15385,
                        'to' => 38461,
                        'prescribed' => 2500,
                        'over' => 30,
                    ],
                    [
                        'from' => 38462,
                        'to' => 153845,
                        'prescribed' => 9423.08,
                        'over' => 32,
                    ],
                    [
                        'from' => 153846,
                        'to' => 99999999999999999,
                        'prescribed' => 46346.15,
                        'over' => 35,
                    ],
                ],
                'semi-monthly' => [
                    [
                        'from' => 10417,
                        'to' => null,
                        'prescribed' => 0,
                        'over' => 0,
                    ],
                    [
                        'from' => 10417,
                        'to' => 16666,
                        'prescribed' => 0,
                        'over' => 20,
                    ],
                    [
                        'from' => 16667,
                        'to' => 33332,
                        'prescribed' => 1250,
                        'over' => 25,
                    ],
                    [
                        'from' => 33333,
                        'to' => 83332,
                        'prescribed' => 5416.67,
                        'over' => 30,
                    ],
                    [
                        'from' => 83333,
                        'to' => 333332,
                        'prescribed' => 20416.67,
                        'over' => 32,
                    ],
                    [
                        'from' => 333333,
                        'to' => 99999999999999999,
                        'prescribed' => 100416.67,
                        'over' => 35,
                    ],
                ],
                'monthly' => [
                    [
                        'from' => 20833,
                        'to' => null,
                        'prescribed' => 0,
                        'over' => 0,
                    ],
                    [
                        'from' => 20833,
                        'to' => 33332,
                        'prescribed' => 0,
                        'over' => 15,
                    ],
                    [
                        'from' => 333333,
                        'to' => 66666,
                        'prescribed' => 1875,
                        'over' => 20,
                    ],
                    [
                        'from' => 66667,
                        'to' => 166666,
                        'prescribed' => 8541.80,
                        'over' => 25,
                    ],
                    [
                        'from' => 166667,
                        'to' => 666666,
                        'prescribed' => 33541.80,
                        'over' => 30,
                    ],
                    [
                        'from' => 666667,
                        'to' => 99999999999999999,
                        'prescribed' => 183541.80,
                        'over' => 35,
                    ],
                ],
            ];
        });
    }
}
