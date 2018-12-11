<?php
namespace Rozdol\Payroll;

use Rozdol\Dates\Dates;
use Rozdol\Utils\Utils;
use Rozdol\Html\Html;

class Payroll
{
    private static $hInstance;

    public static function getInstance()
    {
        if (!self::$hInstance) {
            self::$hInstance = new Payroll();
        }
        return self::$hInstance;
    }

    public function __construct()
    {
            $this->dates = new Dates();
            $this->utils = new Utils();
            $this->html = new Html();
    }

    public function payslip($payslip = [])
    {
        //echo $this->html->pre_display($payslip,"payslip");
        //Get dates
        if ($payslip[no]>$payslip[epmloyee][salaries_per_year]) {
            $payslip[annual_info]="No $payslip[no]th salary allowed";
            return $payslip;
        }
        $before_employment=$this->dates->F_dateadd_month($payslip[epmloyee][df], -1);
        $after_employment=$this->dates->F_dateadd_month($payslip[epmloyee][dt], 1);
        if ((($this->dates->is_earlier($payslip[date], $before_employment))||($this->dates->is_later($payslip[date], $after_employment)))&&($payslip[no]<=12)) {
            $payslip[annual_info]="Emplyee was not emplyed at this date $payslip[date] ($before_employment - $after_employment)";
            return $payslip;
        }
        $salaries=$payslip[salaries];
        $deductions=$payslip[deductions];
        $contributions=$payslip[contributions];
        reset($salaries);
        $payslip[date_start]=key($salaries);
        foreach ($salaries as $date => $salary_amount) {
            if ($this->dates->is_earlier($date, $payslip[date])) {
                $payslip[last_salary_set]=$salary_amount;
            }
        }
        end($salaries);
        //$payslip[last_salary_set]=end($salaries);
        //if($payslip[last_salary_set]==0)$payslip[date_retired]=key($salaries);
        if ($payslip[last_salary_set]==0) {
            $payslip[last_salary_set]=prev($salaries);
        }
        //$payslip[last_salary_set]=9;
        //echo $this->html->pre_display(key($salaries),"test");
        $this_month=$payslip[no];
        if ($this_month>12) {
            $this_month=12;
        }
        $payslip[month_no]=$this_month;
        $payslip[month]=$GLOBALS[Monthesfull][$this_month];

        $year=$this->dates->F_extractyear($payslip[date]);
        $payslip[year]=$year;

        $annual_salary=$this->calc_annual_salary($payslip[date], $payslip[salaries], $payslip[epmloyee][salaries_per_year]);

        //echo $this->html->html->pre_display($annual_salary,"annual_salary");

        $payslip[annual_salary]=$annual_salary[annual_salary];
        $payslip[thirteenth]=$annual_salary[thirteenth];
        $payslip[avg_salary]=$annual_salary[avg_salary];


        /// Anual extra income
        $annual_extra_income=$this->calc_annual_salary($payslip[date], $payslip[extra_income], 12);
        if ($annual_extra_income[annual_salary]>0) {
            $payslip[annual_info].="Employee has $annual_extra_income[annual_salary] extra anual income received from the 3rd patry.\n";
        }
        $payslip[annual_extra_income]=$annual_extra_income[annual_salary];


        /// Anual allowances
        $annual_allowance=$this->calc_annual_salary($payslip[date], $payslip[allowances], 12);
        if ($annual_allowance[annual_salary]>0) {
            $payslip[annual_info].="Employee has $annual_allowance[annual_salary] allowance on anunal income tax amount for Life Insuranse\n";
        }
        $payslip[annual_allowance_life_insur]=$annual_allowance[annual_salary];

        $amount_si = $this->calc_tax($payslip[avg_salary], $deductions[si][calc])[tax];
        $annual_si=$amount_si*$payslip[epmloyee][salaries_per_year];
        $payslip[amount_si] = $amount_si;
        $payslip[annual_si] = $annual_si;
        $payslip[annual_allowance]=$annual_allowance[annual_salary]+$annual_si;
        //echo "NR:".$payslip[epmloyee][non_resident]."<br>";
        if (($payslip[annual_salary]>100000)&&($payslip[epmloyee][non_resident]=='t')) {
            $payslip[annual_info].="Employee has 50% allowance on anunal income tax amount\n";
            $payslip[annual_allowance]=$payslip[annual_allowance]+($payslip[annual_salary]/2);
        }



        $payslip[annual_taxable_amount]=$payslip[annual_salary]-$payslip[annual_allowance]+$payslip[annual_extra_income];

        $payslip[last_salary] = $this->salary($payslip[date], $salaries);
        if ($payslip[no]>12) {
            if ($payslip[last_salary_set]!=$payslip[thirteenth]) {
                $payslip[annual_info].="\nThe $payslip[no]th salary (EUR ".$this->html->money($payslip[thirteenth])."). It is noted that the employee commenced employment or changed his possition on ".$payslip[epmloyee][df].".";
            }
            $payslip[last_salary]=$payslip[thirteenth];
            $payslip[last_salary_set]=$payslip[thirteenth];
        }

        foreach ($deductions as $key => $value) {
            $amount=$payslip[last_salary];
            if ($value[base]=='y') {
                $amount=$payslip[annual_taxable_amount];
            }
            if (($payslip[no]>12)&&($value[title]=='Income tax')) {
                $amount=0;
                $payslip[annual_info].="\nOn $payslip[no]th salary employee is exempt from $value[title]";
            };
            $tax = $this->calc_tax($amount, $value[calc]);
            if ($value[base]=='y') {
                $tax[tax]=round($tax[tax]/12, 2);
            }
            $emply_tax[]=[
                'title' => $value[title],
                'amount' => $tax[tax],
                'info' => $tax[info],
            ];

            $payslip[deductions_total]+=$tax[tax];
        }
        $payslip[employee_pays]=$emply_tax;
        $payslip[net_salary]=$payslip[last_salary]-$payslip[deductions_total];


        foreach ($contributions as $key => $value) {
            $amount=$payslip[last_salary];
            if ($value[base]=='y') {
                $amount=$payslip[annual_taxable_amount];
            }
            $tax = $this->calc_tax($amount, $value[calc]);
            if ($value[base]=='y') {
                $tax[tax]=round($tax[tax]/12, 2);
            }
            $emplr_tax[]=[
                'title' => $value[title],
                'amount' => $tax[tax],
                'info' => $tax[info],
            ];
            $payslip[contributions_total]+=$tax[tax];
        }
        $payslip[employer_pays]=$emplr_tax;
        $payslip[gross_salary]=$payslip[last_salary];
        $payslip[cost_to_employer]=$payslip[gross_salary]+$payslip[contributions_total];
        $to_date[salary]=$payslip[gross_salary]*$payslip[no];
        $to_date[deductions]=$payslip[deductions_total]*$payslip[no];
        $to_date[contributions]=$payslip[contributions_total]*$payslip[no];
        $payslip[to_date]=$to_date;
        //echo $this->html->pre_display($payslip,"payslip");
        return $payslip;
    }
    public function calc_annual_salary($date = '', $salaries = [], $salaries_count = 12)
    {
        $year=$this->dates->F_extractyear($date);
        //echo $this->html->pre_display($salaries,"salaries");
        $month_emplyed=0;
        $max_salary=0;
        for ($month=1; $month <= 12; $month++) {
            $mo=sprintf('%02d', $month);
            $df="01.".$mo.".$year";
            $dt=$this->dates->lastday_in_month($df);

            $is_employed = $this->is_employed($df, $salaries);
            //echo $this->html->pre_display($is_employed,"M:$month is_employed $df - $dt");
            $annual_salary+=$is_employed[salary];
            //echo "$month $df - $dt S:$is_employed[salary] ($annual_salary)<br>";
            $info[]="$df-$dt = $is_employed[salary]";
            if ($is_employed[salary]>0) {
                $month_emplyed++;
            }
            if ($is_employed[salary]>$max_salary) {
                $max_salary=$is_employed[salary];
            }
        }
        //echo $this->html->pre_display($info,"info");
        $last_salary=$is_employed[salary];
        $avg_salary=$annual_salary/12;

        if ($salaries_count>12) {
            $extra_salaries=$salaries_count-12;
            $extra_salary=$extra_salaries*$annual_salary/12;
            $thirteenth=$annual_salary/12;
            $info[]="$extra_salaries salary extra= $extra_salary";
            $annual_salary+=$extra_salary;
        }
        $data[info]=$info;
        $data[thirteenth]=$thirteenth;
        $data[avg_salary]=$avg_salary;
        $data[last_salary]=$last_salary;
        $data[annual_salary]=$annual_salary;
        $data[month_emplyed]=$month_emplyed;
        $data[max_salary]=$max_salary;
        $data[extra_salary]=$extra_salary;
        //echo $this->html->pre_display($data,"calc_annual_salary");
        return $data;
    }
    public function salary($date = '', $data = [])
    {
        $dt=$this->dates->lastday_in_month($date);
        $salary=0;
        foreach ($data as $key => $value) {
            if (($this->dates->is_earlier($key, $dt))&&($value>0)) {
                $salary=$value;
            }
        }
        return $salary;
    }
    public function is_employed($date = '', $data = [])
    {
        //echo $this->html->pre_display($data,"is_employed:$date data");
        $employed=0;
        $salary=0;
        $employed_this_month=0;
        $retired_this_month=0;
        $month_emplyed=0;
        $mo0=$this->dates->F_extractmonth($date).'.'.$this->dates->F_extractyear($date);
        $df=$this->dates->firstday_in_month($date);
        $dt=$this->dates->lastday_in_month($date);
        foreach ($data as $key => $value) {
            $i++;
            $mo1=$this->dates->F_extractmonth($key).'.'.$this->dates->F_extractyear($key);
            //$df1=$this->dates->firstday_in_month($key);
            //$dt1=$this->dates->lastday_in_month($key);
            //echo "$key:$df-$dt<br>";
            if (($this->dates->is_earlier($key, $dt))&&($value>0)) {
                $salary=$value;
                $month_emplyed++;
                $employed=1;
            }
            if (($this->dates->is_earlier($key, $dt))&&($value==0)) {
                $employed=0;
            }
            if (($mo1==$mo0)&&($value>0)&&($i==1)) {
                $employed_this_month=1;
                $date_employed=$key;
                $employed=1;
                $salary=$value;
            }
            if (($mo1==$mo0)&&($value==0)) {
                $retired_this_month=1;
                $employed=1;
                $date_retired=$key;
            }
            if ($employed==0) {
                $salary=0;
            }

            //echo "$key => $value ($mo==$mo0)<br>";
        }
        if ($employed_this_month) {
            $dlast=$this->dates->lastday_in_month($date_employed);
            $dfirst=$this->dates->firstday_in_month($date_employed);
            //$days=$this->dates->F_datediff($date_employed,$dlast);
            $wdays=$this->dates->getWorkingDays($date_employed, $dlast);
            $allwdays=$this->dates->getWorkingDays($dfirst, $dlast);
            $salary=round($salary*($wdays/$allwdays), 2);
            //echo "Days W1:$days, wD1:$wdays, S1:$salary<br>";
        }
        if ($retired_this_month) {
            $dfirst=$this->dates->firstday_in_month($date_retired);
            $dlast=$this->dates->lastday_in_month($date_retired);
            //$days=$this->dates->F_datediff($dfirst,$date_retired)+1;
            $wdays=$this->dates->getWorkingDays($dfirst, $date_retired);
            $allwdays=$this->dates->getWorkingDays($dfirst, $dlast);
            $salary=round($salary*($wdays/$allwdays), 2);
            //echo "Days W2:$days, wD2:$wdays, S2:$salary<br>";
        }
        //echo "S:$salary, E:$employed, EM:$employed_this_month, RM:$retired_this_month<br>";
        $data[df]=$df;
        $data[dt]=$dt;
        $data[salary]=$salary;
        $data[employed]=$employed;
        $data[employed_this_month]=$employed_this_month;
        $data[retired_this_month]=$retired_this_month;
        $data[month_emplyed]=$month_emplyed;
        //echo $this->html->pre_display($data,"is_employed");
        return $data;
    }

    public function calc_tax($amount, $data)
    {
        $balance=$amount;
        end($data);
        $last_key=key($data);
        $last_val=$data[$last_key];
        if ($amount>$last_key) {
            $data[$amount]=$last_val;
        }
        $out=$this->html->tablehead('', '', '', '', ['i','key','value','inc_amount','%','calc_amount','tax','tax_total','balance']);
        foreach ($data as $key => $value) {
            $i++;
            $inc_amount=$key-$prev_key;

            $diff=$amount-$inc_amount;
            if ($diff<0) {
                $calc_amount=$amount;
            } else {
                $calc_amount=$inc_amount;
            }
            if ($balance<$calc_amount) {
                $calc_amount=$balance;
            }
            if ($calc_amount<0) {
                $calc_amount=0;
            }
            $prc = $prev_val;
            $tax=round(($calc_amount*$prc), 2);
            $tax_total+=$tax;
            $out.=$this->html->tr([$i,$key,$value,$inc_amount,$prc,$calc_amount,$tax,$tax_total,$balance]);
            $balance-=$inc_amount;

            //echo "$key => $value,  INC:=$inc_amount, DIFF:$diff,  DIFF2:$diff2,$calc_amount @ $prc % = $tax<hr>";
            if ($key!=0) {
                $info[]="$calc_amount @ $prc % = $tax";
            }
            $prev_key=$key;
            $prev_val=$value;
            $prc=$value;
        }
        $data[tax]=$tax_total;
        $data[info]=$info;
        $out.=$this->html->tablefoot();
        //echo $out;
        return $data;
    }

    function test()
    {
        return "ok";
    }
}
