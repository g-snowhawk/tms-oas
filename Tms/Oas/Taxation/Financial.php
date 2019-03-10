<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2020 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Oas\Taxation;

use DateTime;
use P5\Lang;
use Tms\Pdf;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Financial extends \Tms\Oas\Taxation
{
    const TEXT_COLOR = [0,66,99];
    const BASIC_DEDUCTION = 650000;

    private $sum_income = 0;
    private $sum_buying = 0;
    private $sum_fixedasset = 0;

    private $investments = 0;
    private $withdrawals = 0;
    private $capital = 0;

    private $column33 = 0;
    private $column43 = 0;

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => Lang::translate('HEADER_TITLE'), 'id' => 'osa-taxation-financial', 'class' => 'taxation']
        );

        $paths = $this->view->getPaths();
        $this->pdf = new Pdf($paths);
    }

    /**
     * Default view.
     */
    public function defaultView() : void
    {
        $this->checkPermission('oas.taxation.read');

        $template_path = 'oas/taxation/financial.tpl';
        $html_id = 'oas-taxation-financial';

        $form_params = $this->view->param('form');
        if (is_null($form_params)) {
            $form_params = [];
        }
        $form_params['target'] = 'TmsPDFWindow';
        $this->view->bind('form', $form_params);

        $this->setHtmlId($html_id);
        $this->view->render($template_path);
    }

    public function pdf() : void
    {
        $tYear = $this->request->POST('nendo') . '-01-01';

        $this->pdf->loadTemplate("oas/taxation/financial.pdf");

        $this->page2($tYear);
        $this->page3($tYear);
        $this->page1($tYear);
        $this->pdf->movePage(3, 1);
        $this->page4($tYear);
        $this->pdf->setPage(2, false);
        $this->drawDeduction();
                
        $year = date('Y', strtotime($tYear));
        $file = $this->getPdfPath($year, 'taxation', 'financialsheet.pdf');
        $locked = ($this->request->POST('locked') === '1') ? true : false;
        $this->outputPdf(basename($file), dirname($file), true, $locked);
    }

    private function page1($target_year)
    {
        $this->pdf->addPageFromTemplate(1, 'L');

        $this->drawHeader($target_year);
        $income = $this->drawIncome($target_year);
        $buying = $this->drawBuying($target_year);
        $cost   = $this->drawCost($target_year);
        $data = [
            'no07' => $income - $buying,
            'no37' => 0,
            'no42' => 0,
        ];
        $data['no33'] = $data['no07'] - $cost;
        $data['no43'] = $data['no33'] + $data['no37'] - $data['no42'];
        $data['no44'] = min($data['no43'], self::BASIC_DEDUCTION);
        $data['no45'] = $data['no43'] - $data['no44'];
        $ary = [
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'no07', 'suffix' => '', 'x' =>  59.5, 'y' => 125.5, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'no33', 'suffix' => '', 'x' => 145.8, 'y' => 182.7, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'no43', 'suffix' => '', 'x' => 232.2, 'y' => 135.0, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'no44', 'suffix' => '', 'x' => 232.2, 'y' => 141.5, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'no45', 'suffix' => '', 'x' => 232.2, 'y' => 151.0, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
        ];
        $this->pdf->draw($ary, $data);

        $sql = "SELECT COUNT(year) AS cnt 
                  FROM `table::account_book`
                 WHERE year = ?";

        $year = date('Y', strtotime($target_year));
        $exists = $this->db->query($sql, [$year])->fetchColumn(0);

        $sql = ($exists > 0) 
            ? "UPDATE `table::account_book` SET bol_01 = ?, col_01 = ?  WHERE userkey = ? AND year = ?"
            : "INSERT INTO `table::account_book` (bol_01, col_01, userkey, year) VALUES (?,?,?,?)";
        if (false === $this->db->query($sql, [$income, $data['no45'], $this->uid, $year])) {
            trigger_error($this->db->error());
        }

        $this->column33 = $data['no33'];
        $this->column43 = $data['no43'];
    }

    private function page2($target_year)
    {
        $this->pdf->addPageFromTemplate(2, 'L');

        $data = [
            'nengo' => $this->toWareki($target_year),
            'name'  => $this->userinfo['fullname'],
            'rubi'  => $this->userinfo['fullname_rubi'],
        ];
        $ary = [
            ['font' => $this->mincho, 'style' => '', 'size' =>  6, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'rubi',  'suffix' => '', 'x' =>   70, 'y' => 13.0, 'type' => 'Cell', 'width' => 35, 'height' =>   4, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'name',  'suffix' => '', 'x' =>   70, 'y' => 16.0, 'type' => 'Cell', 'width' => 35, 'height' =>   5, 'align' => 'L', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 12, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'nengo', 'suffix' => '', 'x' => 32.0, 'y' =>  7.8, 'type' => 'Cell', 'width' => 10, 'height' => 6.2, 'align' => 'R', 'flg' => true, 'pitch' => 3.5],
        ];
        $this->pdf->draw($ary, $data);
        // 
        $this->sum_income = $this->drawIncomeDetail($target_year);
        $this->sum_buying = $this->drawBuyingDetail($target_year);
    }

    private function page3($target_year)
    {
        $this->pdf->addPageFromTemplate(3, 'L');
        $this->sum_fixedasset = $this->drawFixedassetsDetail($target_year);
    }

    private function page4($target_year)
    {
        $this->pdf->addPageFromTemplate(4, 'L');

        $year = date('Y', strtotime($target_year));
        $data['year']   = $this->toWareki($target_year);
        $data['sMonth'] = '1';
        $data['sDay']   = '1';
        $data['eMonth'] = '12';
        $data['eDay']   = '31';

        $ary = [
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'year',   'suffix' => '', 'x' => 170.2, 'y' => 19.3, 'type' => 'Cell', 'width' =>   6, 'height' => 4.5, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'eMonth', 'suffix' => '', 'x' => 178.2, 'y' => 19.3, 'type' => 'Cell', 'width' =>   6, 'height' => 4.5, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'eDay',   'suffix' => '', 'x' => 186.2, 'y' => 19.3, 'type' => 'Cell', 'width' =>   6, 'height' => 4.5, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'sMonth', 'suffix' => '', 'x' =>  49.5, 'y' => 30.4, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'sDay',   'suffix' => '', 'x' =>  58.0, 'y' => 30.4, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'eMonth', 'suffix' => '', 'x' =>  79.5, 'y' => 30.4, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'eDay',   'suffix' => '', 'x' =>    88, 'y' => 30.4, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'sMonth', 'suffix' => '', 'x' => 140.5, 'y' => 30.4, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'sDay',   'suffix' => '', 'x' => 148.5, 'y' => 30.4, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'eMonth', 'suffix' => '', 'x' => 170.5, 'y' => 30.4, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'eDay',   'suffix' => '', 'x' => 179.0, 'y' => 30.4, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true],
        ];
        $this->pdf->draw($ary, $data);
        $this->drawPlus($year);
        $this->drawPlus($year + 1, $year);
        $this->drawMinus($year);
        $this->drawMinus($year + 1, $year);
    }

    private function drawIncomeDetail($target_year)
    {
        $start = date("Y-01-01 00:00:00", strtotime($target_year));
        $end   = date("Y-12-31 23:59:59", strtotime($target_year));
        $item_code = $this->filter_items['SALES'];

        $sql = function($col) {
            return "SELECT DATE_FORMAT(issue_date, '%c') AS month,
                           SUM(amount_{$col}) AS amount  
                      FROM `table::transfer`
                     WHERE item_code_{$col} = ? 
                       AND (issue_date >= ? AND issue_date <= ?)
                  GROUP BY month
                  ORDER BY month";
        };

        if (!$this->db->query($sql('left'), [$item_code, $start, $end])) {
            return false;
        }
        $result2 = $this->db->fetchAll();
        $umount = [];
        foreach ($result2 as $unit) {
            $umount[$unit['month']] = $unit['amount'];
        }

        if (!$this->db->query($sql('right'), [$item_code, $start, $end])) {
            return false;
        }
        $result = $this->db->fetchAll();

        $data = [];
        $ary  = [];
        $total = 0;
        foreach ($result as $unit) {
            $amount = $unit['amount'];
	    if (isset($umount[$unit['month']])) {
                $amount -= $umount[$unit['month']];
            }
            $data[$unit['month']] = number_format($amount);
            $total += $amount;
        }
        ksort($data);
        $y = 37;
        $x = 31.5;
        $w = 44.7;
        $h = 6.3;
        $t = 3.0;
        for ($cnt = 1; $cnt <= 12; $cnt++) {
            $ary[] = [
                'font' => $this->mono,
                'style' => '',
                'size' => 10,
                'color' => self::TEXT_COLOR,
                'prefix' => '',
                'name' => $cnt,
                'suffix' => '',
                'x' => $x,
                'y' => $y,
                'type' => 'Cell',
                'width' => $w - 2,
                'height' => $h,
                'align' => 'R',
                'flg' => true
            ];
            $y += $h;
        }

        $sql = function($col) {
            return "SELECT item_code_{$col},
                           SUM(amount_{$col}) AS amount  
                      FROM `table::transfer`
                     WHERE item_code_{$col} = ? 
                       AND (issue_date >= ? AND issue_date <= ?)
                  GROUP BY item_code_{$col}";
        };

        // home use
        $item_code = $this->filter_items['HOUSEWORK'];
        if (!$this->db->query($sql('right'), [$item_code, $start, $end])) {
            return false;
        }
        $result = $this->db->fetch();
        $data[$item_code] = $result['amount'];
        $total += $result['amount'];
        $ary[] = [
            'font' => $this->mono,
            'style' => '',
            'size' => 10,
            'color' => self::TEXT_COLOR,
            'prefix' => '',
            'name' => $item_code,
            'suffix' => '',
            'x' => $x - 1.8,
            'y' => $y,
            'type' => 'Cell',
            'width' => $w,
            'height' => $h,
            'align' => 'R',
            'flg' => true,
            'pitch' => $t
        ];
        $y += $h;

        // other use
        $item_code = $this->filter_items['MISCELLANOUS_INCOME'];
        if (!$this->db->query($sql('right'), [$item_code, $start, $end])) {
            return false;
        }
        $result = $this->db->fetch();
        $data[$item_code] = $result['amount'];
        $total += $result['amount'];
        $ary[] = [
            'font' => $this->mono,
            'style' => '',
            'size' => 10,
            'color' => self::TEXT_COLOR,
            'prefix' => '',
            'name' => $item_code,
            'suffix' => '',
            'x' => $x - 1.8,
            'y' => $y,
            'type' => 'Cell',
            'width' => $w,
            'height' => $h,
            'align' => 'R',
            'flg' => true,
            'pitch' => $t
        ];
        $y += $h;

        // Total
        $data['total'] = $total;
        $ary[] = [
            'font' => $this->mono,
            'style' => '',
            'size' => 10,
            'color' => self::TEXT_COLOR,
            'prefix' => '',
            'name' => 'total',
            'suffix' => '',
            'x' => $x - 1.8,
            'y' => $y + 3.5,
            'type' => 'Cell',
            'width' => $w,
            'height' => $h,
            'align' => 'R',
            'flg' => true,
            'pitch' => $t
        ];

        $this->pdf->draw($ary, $data);

        return $total;
    }

    private function drawBuyingDetail($target_year)
    {
        $start = date("Y-01-01 00:00:00", strtotime($target_year));
        $end   = date("Y-12-31 23:59:59", strtotime($target_year));
        $item_code = $this->filter_items['SALES'];

        $sql = function($col) {
            return "SELECT DATE_FORMAT(issue_date, '%c') AS month,
                           SUM(amount_{$col}) AS amount  
                      FROM `table::transfer`
                     WHERE item_code_{$col} = ? 
                       AND category NOT IN ('A','Z')
                       AND (issue_date >= ? AND issue_date <= ?)
                  GROUP BY month
                  ORDER BY month";
        };

        if (!$this->db->query($sql('left'), [$item_code, $start, $end])) {
            return false;
        }
        $result = $this->db->fetchAll();
        $data = [];
        $ary  = [];
        $total = 0;
        foreach ($result as $unit) {
            $data[$unit['month']] = number_format($unit['amount']);
            $total += $unit['amount'];
        }
        ksort($data);
        $y = 37;
        $x = 77.3;
        $w = 44.7;
        $h = 6.3;
        $t = 3.0;
        for ($cnt = 1; $cnt <= 12; $cnt++) {
            $ary[] = [
                'font' => $this->mono,
                'style' => '', 
                'size' => 10,
                'color' => self::TEXT_COLOR, 
                'prefix' => '',
                'name' => $cnt,
                'suffix' => '',
                'x' => $x,
                'y' => $y,
                'type' => 'Cell',
                'width' => $w - 2,
                'height' => $h,
                'align' => 'R',
                'flg' => true
            ];
            $y += $h;
        }
        $y += $h * 2;

        // Total
        $data['total'] = $total;
        $ary[] = [
            'font' => $this->mono,
            'style' => '', 
            'size' => 10,
            'color' => self::TEXT_COLOR, 
            'prefix' => '',
            'name' => 'total',
            'suffix' => '',
            'x' => $x - 1.8,
            'y' => $y + 3.5,
            'type' => 'Cell',
            'width' => $w,
            'height' => $h,
            'align' => 'R',
            'flg' => true,
            'pitch' => $t
        ];

        $this->pdf->draw($ary, $data);

        return $total;
    }

    public function drawFixedassetsDetail($target_year)
    {
        $sql = "SELECT * 
                  FROM `table::fixed_assets`
                 WHERE quantity > 0"; 
        if (!$this->db->query($sql)) {
            return 0;
        }

        $total = 0;
        $dpsum = 0;
        $spsum = 0;
        $ohsum = 0;
        $tysum = 0;

        $y = 35.4;
        $lh = 7.15;
        $line = 0;

        while ($result = $this->db->fetch()) {
            $t = date("Y", strtotime($target_year));
            $s = date("Y", strtotime($result['acquire']));

            $cln = clone $this->db;
            $sql = "SELECT * 
                      FROM `" . $cln->TABLE('fixed_assets_detail') . "` 
                     WHERE id = " . $cln->quote($result['id']) . "
                       AND year = " . $cln->quote($t);
            if (false !== $cln->query($sql)) {
                while ($ido = $cln->fetch()) {
                    $result['ido'] = $ido;
                    $result['note'] = $ido['note'];
                }
            }

            //
            if ($result['item'] === $this->filter_items['LUMPSUM_DEPRECIABLE_ASSETS']) {
                $depreciate = floor($result['price'] / 3);
                $price_onhand = $result['price'] - ($depreciate * ($t - $s));
            } else {
                $depreciate_price = Tms_Pms_Accounting_Fixedasset_Pdf::depreciate($s, $t - 1, $result);
                $price_onhand = ($result['quantity'] * $result['price']) - $depreciate_price;
                $tp = Tms_Pms_Accounting_Fixedasset_Pdf::depreciate($t, $t, $result);
                if ($tp > $price_onhand) {
                    $depreciate_price = $price_onhand;
                    $memValue = 1;
                } else {
                    $depreciate_price = $tp;
                    $memValue = 0;
                }
                $depreciate = $depreciate_price - $memValue;
            }
            $dpsum += $depreciate;
            //
            $price_onhand -= $depreciate;

            if ($result['item'] === $this->filter_items['LUMPSUM_DEPRECIABLE_ASSETS'] && $price_onhand < $depreciate) {
                $depreciate += $price_onhand;
                $price_onhand = 0;
            }

            if (isset($result['ido']['month'])) {
                $price_onhand = 0;
            }
            $ohsum += $price_onhand;
            //
            $special = 0;
            $spsum += $special;
            //
            $total += $depreciate - $special;
            $tysum += ($depreciate - $special) * ($result['official_ratio'] / 100);
            // out
            $limit = $s + $result['durability'];
            if (isset($result['ido']['month'])) {
                $months = $result['ido']['month'];
            } else if ($t == $s) {
                $months = 13 - date("n", strtotime($result['acquire']));
            } else if ($t == $limit) {
                $months = 12 - (13 - date("n", strtotime($result['acquire'])));
            } else {
                $months = 12;
            }

            $acyear = $this->toWareki($result['acquire']);

            $data = [
                'name' => $result['title'],
                'quantity' => $result['quantity'],
                'acyear' => $acyear,
                'acmon' => date('n', strtotime($result['acquire'])),
                'price1' => number_format($result['price']),
                'price2' => number_format($result['price']),
                'type' => $result['depreciate_type'],
                'durability' => $result['durability'],
                'rate' => sprintf("%01.3f", $result['depreciate_rate']),
                'months' => $months,
                'depreciate' => number_format($depreciate),
                'depreciatetotal' => number_format($depreciate - $special),
                'official_ratio' => $result['official_ratio'],
                'thisyear' => number_format(($depreciate - $special) * ($result['official_ratio'] / 100)),
                'onhand' => number_format($price_onhand),
            ];

            if ($result['item'] === $this->filter_items['LUMPSUM_DEPRECIABLE_ASSETS']) {
                $data['quantity'] = '-';
                $data['acmon'] = null;
                $data['type'] = '-';
                $data['durability'] = '-';
                $data['rate'] = '1/3';
                $data['months'] = '-';
            }

            // Special
            if ($special > 0) {
                $data['special'] = $special;
            }
            // Note
            $data['note'] = (isset($result['note'])) ? $result['note'] : '';

            $line++;
            $ym = $y + 0.8;
            $ary = [
                ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'name',            'suffix' => '', 'x' =>  19.5, 'y' => $y,  'type' => 'Cell', 'width' => 20.5, 'height' => $lh, 'align' => 'L', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'quantity',        'suffix' => '', 'x' =>  36.0, 'y' => $y,  'type' => 'Cell', 'width' =>  9.8, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'acyear',          'suffix' => '', 'x' =>  49.7, 'y' => $ym, 'type' => 'Cell', 'width' =>    5, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'acmon',           'suffix' => '', 'x' =>  55.5, 'y' => $ym, 'type' => 'Cell', 'width' =>  4.6, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'price1',          'suffix' => '', 'x' =>  61.0, 'y' => $y,  'type' => 'Cell', 'width' =>   19, 'height' =>   4, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'price2',          'suffix' => '', 'x' =>  84.2, 'y' => $y,  'type' => 'Cell', 'width' =>   19, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'type',            'suffix' => '', 'x' => 105.3, 'y' => $y,  'type' => 'Cell', 'width' =>  9.9, 'height' => $lh, 'align' => 'C', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'durability',      'suffix' => '', 'x' =>   115, 'y' => $y,  'type' => 'Cell', 'width' =>    7, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'rate',            'suffix' => '', 'x' => 124.2, 'y' => $y,  'type' => 'Cell', 'width' => 9.85, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 7, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'months',          'suffix' => '', 'x' => 139.0, 'y' => $y,  'type' => 'Cell', 'width' =>  4.5, 'height' =>   4, 'align' => 'C', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'depreciate',      'suffix' => '', 'x' =>   146, 'y' => $y,  'type' => 'Cell', 'width' =>   18, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'special',         'suffix' => '', 'x' =>   166, 'y' => $y,  'type' => 'Cell', 'width' =>   18, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'depreciatetotal', 'suffix' => '', 'x' =>   186, 'y' => $y,  'type' => 'Cell', 'width' => 18.5, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'official_ratio',  'suffix' => '', 'x' =>   207, 'y' => $y,  'type' => 'Cell', 'width' =>    8, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'thisyear',        'suffix' => '', 'x' => 216.5, 'y' => $y,  'type' => 'Cell', 'width' => 18.5, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'onhand',          'suffix' => '', 'x' => 236.6, 'y' => $y,  'type' => 'Cell', 'width' =>   19, 'height' => $lh, 'align' => 'R', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'note',            'suffix' => '', 'x' =>   254, 'y' => $y,  'type' => 'Cell', 'width' =>   20, 'height' => $lh, 'align' => 'L', 'flg' => true],
            ];
            $this->pdf->draw($ary, $data);
            $y += $lh;
        }
        for ($i = $line; $i < 11; $i++) {
            $y += $lh;
        }
        $y += 1.3;
        $lh = 6;
        $data = [
            'depreciate' => number_format($dpsum),
            'depreciatetotal' => number_format($total),
            'thisyear' => number_format($tysum),
            'onhand' => number_format($ohsum),
        ];
        if ($spsum > 0) {
            $data['special'] = $spsum;
        }
        $ary = [
            ['font' => $this->mono, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'depreciate',      'suffix' => '', 'x' =>   146, 'y' => $y, 'type' => 'Cell', 'width' =>   18, 'height' => $lh, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'special',         'suffix' => '', 'x' =>   166, 'y' => $y, 'type' => 'Cell', 'width' =>   18, 'height' => $lh, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'depreciatetotal', 'suffix' => '', 'x' =>   186, 'y' => $y, 'type' => 'Cell', 'width' => 18.5, 'height' => $lh, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'thisyear',        'suffix' => '', 'x' => 216.5, 'y' => $y, 'type' => 'Cell', 'width' => 18.5, 'height' => $lh, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'onhand',          'suffix' => '', 'x' => 236.6, 'y' => $y, 'type' => 'Cell', 'width' =>   19, 'height' => $lh, 'align' => 'R', 'flg' => true],
        ];
        $this->pdf->draw($ary, $data);

        return $total;
    }

    private function drawHeader($target_year)
    {
        $data = [];
        $data['address1'] = $this->userinfo['city'] . $this->userinfo['town'] . $this->userinfo['address1'];
        $data['address2'] = $this->userinfo['address2'];
        $data['company']  = $this->userinfo['company'];
        $data['name']     = $this->userinfo['fullname'];
        $data['rubi']     = $this->userinfo['fullname_rubi'];
        $data['tel']      = $this->userinfo['tel'];

        // fixed properties
        $data['caddress'] = $this->oas_config->caddress;
        $data['works'] = $this->oas_config->works;
        $data['telhome'] = $this->oas_config->homephone;

        // today
        $data['year']  = $this->toWareki(date('Y-m-d'));
        $data['month'] = date('n');
        $data['day']   = date('j');
        $data['nengo'] = $this->toWareki($target_year);
        $data['sMonth'] = '1';
        $data['sDay']   = '1';
        $data['eMonth'] = '12';
        $data['eDay']   = '31';

        $lh =  (empty($data['address2'])) ? 9.2 : 4.5;
        $ary = [
            ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'address1', 'suffix' => '', 'x' => 114.8, 'y' =>   24, 'type' => 'Cell', 'width' =>  56, 'height' => $lh, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'caddress', 'suffix' => '', 'x' => 114.8, 'y' => 33.5, 'type' => 'Cell', 'width' =>  56, 'height' => $lh, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'works',    'suffix' => '', 'x' => 114.8, 'y' =>   43, 'type' => 'Cell', 'width' =>  23, 'height' => $lh, 'align' => 'C', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'company',  'suffix' => '', 'x' => 148.5, 'y' =>   43, 'type' => 'Cell', 'width' =>  23, 'height' => $lh, 'align' => 'C', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  6, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'rubi',     'suffix' => '', 'x' =>   192, 'y' =>   25, 'type' => 'Cell', 'width' =>  35, 'height' =>   4, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'name',     'suffix' => '', 'x' =>   192, 'y' =>   28, 'type' => 'Cell', 'width' =>  35, 'height' =>   5, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'telhome',  'suffix' => '', 'x' =>   197, 'y' =>   34, 'type' => 'Cell', 'width' =>  28, 'height' => 4.5, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'tel',      'suffix' => '', 'x' =>   197, 'y' =>   38, 'type' => 'Cell', 'width' =>  28, 'height' => 4.5, 'align' => 'L', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 12, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'nengo',    'suffix' => '', 'x' => 113.5, 'y' => 14.0, 'type' => 'Cell', 'width' =>  12, 'height' => 6.2, 'align' => 'R', 'flg' => true, 'pitch' => 3.5],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'sMonth',   'suffix' => '', 'x' => 155.3, 'y' => 63.6, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'sDay',     'suffix' => '', 'x' => 170.5, 'y' => 63.6, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'eMonth',   'suffix' => '', 'x' => 191.0, 'y' => 63.6, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'eDay',     'suffix' => '', 'x' => 206.4, 'y' => 63.6, 'type' => 'Cell', 'width' => 9.8, 'height' => 6.2, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'year',     'suffix' => '', 'x' =>    23, 'y' => 62.8, 'type' => 'Cell', 'width' =>   6, 'height' => 4.5, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'month',    'suffix' => '', 'x' =>    32, 'y' => 62.8, 'type' => 'Cell', 'width' =>   6, 'height' => 4.5, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'day',      'suffix' => '', 'x' =>    41, 'y' => 62.8, 'type' => 'Cell', 'width' =>   6, 'height' => 4.5, 'align' => 'R', 'flg' => true],
        ];
        if (!empty($data['address2'])) {
            $ary[] = ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'address2', 'suffix' => '', 'x' => 123, 'y' => 30.5, 'type' => 'Cell', 'width' => 56, 'height' => $lh, 'align' => 'L', 'flg' => true];
        }
        $this->pdf->draw($ary, $data);
    }

    private function drawIncome($target_year)
    {
        $start = date("Y-01-01 00:00:00", strtotime($target_year));
        $end   = date("Y-12-31 23:59:59", strtotime($target_year));
        $sql = "SELECT ai.item_code AS code,
                       SUM(td.amount_right) AS amount,
                       MIN(ai.item_name) AS label
                FROM `table::account_items` ai
                LEFT JOIN (
                    SELECT * 
                      FROM `table::transfer`
                     WHERE userkey = ?
                       AND (issue_date >= ? AND issue_date <= ?)
                ) td
                ON td.item_code_right = ai.item_code
                WHERE (ai.item_code = 8112 OR ai.item_code = 8791)
                GROUP BY code";
        if (!$this->db->query($sql, [$this->uid, $start, $end])) {
            echo $this->db->error();
            exit;
            return false;
        }
        $result = $this->db->fetchAll();

        $data  = [];
        $total = $this->sum_income;
        foreach ($result as $val) {
            if (empty($val['amount'])) {
                continue;
            }
        }

        ksort($data);

        $y = 81;
        $x = 59.58;
        $w = 44.7;
        $h = 6.16;
        $t = 3.0;
        $ary = [];

        $data['total'] = $total;
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'total', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];

        $this->pdf->draw($ary, $data);

        return $total;
    }

    private function drawBuying($target_year)
    {
        $start = date("Y-01-01 00:00:00", strtotime($target_year));
        $end   = date("Y-12-31 23:59:59", strtotime($target_year));

        $data  = [];
        $total = $this->sum_buying;

        foreach (array(8211,8221,8241) as $key) {

            $where = ($key === 8221) ? " AND category NOT IN ('A','Z')" : '';
            $lr = ($key !== 8241) ? 'right' : 'left';

            $sql = "SELECT ai.item_code AS code,
                           SUM(td.amount_{$lr}) AS amount,
                           MIN(ai.item_name) AS label
                      FROM `table::account_items` ai
                      LEFT JOIN (
                          SELECT * 
                            FROM `table::transfer`
                           WHERE userkey = ?
                             AND (issue_date >= ? AND issue_date <= ?){$where}
                      ) td
                        ON td.item_code_{$lr} = ai.item_code
                     WHERE ai.item_code = ?
                     GROUP BY code";

            if (!$this->db->query($sql, [$this->uid, $start, $end, $key])) {
                return false;
            }

            while ($result = $this->db->fetch()) {
                $data[$result['code']] = $result['amount'];
                if (empty($result['amount'])) {
                    continue;
                }
            }
        }

        ksort($data);

        $y = 90.5;
        $x = 59.58;
        $w = 44.7;
        $h = 6.35;
        $t = 3.0;
        $ary = [];
        foreach ($data as $key => $val) {
            if ($key === 8241) {
                continue;
            }
            $ary [] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => $key, 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
            $y += $h;
        }

        $purchase = $this->filter_items['PURCHASE'];
        $beginning_inventory = $this->filter_items['BEGINNING_INVENTORY'];
        $periodend_inventory = $this->filter_items['PERIODEND_INVENTORY'];

        $data['total'] = $data[$beginning_inventory] + $data[$purchase];
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'total', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h;

        $periodend_inventory = $this->filter_items['PERIODEND_INVENTORY'];
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => $periodend_inventory, 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h;

        $data['offset'] = $data['total'] - $data[$periodend_inventory];
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'offset', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];

        $this->pdf->draw($ary, $data);

        return $data['offset'];
    }

    private function drawCost($target_year)
    {
        $start = date("Y-01-01 00:00:00", strtotime($target_year));
        $end   = date("Y-12-31 23:59:59", strtotime($target_year));

        $sql = function($col) {
            return "SELECT ai.item_code AS code, 
                           SUM(td.amount_{$col}) AS amount, 
                           MIN(ai.item_name) AS label,
                           MIN(ai.financial_order) AS financial_order,
                           CASE WHEN MIN(financial_order) <> 25 THEN 0
                                WHEN SUM(td.amount_{$col}) > 0 THEN 0
                                ELSE 1
                            END AS sorter
                      FROM `table::account_items` ai
                      LEFT JOIN (
                          SELECT * FROM `table::transfer`
                           WHERE userkey = ?
                             AND (issue_date >= ? AND issue_date <= ?)
                      ) td
                        ON td.item_code_{$col} = ai.item_code
                     WHERE ai.financial_order IS NOT NULL
                     GROUP BY code ORDER BY financial_order, sorter, code";
        };

        if (!$this->db->query($sql('left'), [$this->uid, $start, $end])) {
            return false;
        }

        $data1 = [];
        $order = [];
        $label = [];
        $subtotal = 0;
        $total = 0;
        $skip = 0;
        while ($val = $this->db->fetch()) {
            $data1[$val['code']] = (int)$val['amount'];
            $order[$val['code']] = (int)$val['financial_order'];
            $total += (int)$val['amount'];
            if ($val['financial_order'] >= 8 && $val['financial_order'] <= 31) {
                $subtotal += (int)$val['amount'];
            }
            if ($val['financial_order'] >= 25 && $val['financial_order'] <= 30) {
                $label[$val['code']] = $val['label'];
            }
        }
        if (!$this->db->query($sql('right'), [$this->uid, $start, $end])) {
            return false;
        }
        while ($val = $this->db->fetch()) {
            if (isset($data1[$val['code']])) {
                $data1[$val['code']] -= $val['amount'];
            }
            if (empty($val['amount'])) {
                continue;
            }
            $total -= $val['amount'];
        }

        ksort($label);

        $y = 135.0;
        $x = 59.58;
        $w = 44.7;
        $h = 6.35;
        $t = 3.0;

        $ary = [];
        $lab = [];
        foreach ($data1 as $key => $val) {
            if (!empty($val)) {
                if (isset($label[$key])) {
                    $lab[] = ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => $key, 'suffix' => '', 'x' => 111.5, 'y' => $y, 'type' => 'Cell', 'width' => 18, 'height' => $h, 'align' => 'J', 'flg' => true];
                }
                $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => $key, 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
            }

            $y += $h;
            if ($order[$key] === 16) {
                $y = 78.0;
                $x = 145.8;
            }
            $data[$key] = $val;
        }

        $data['total'] = $total;
        $y = 173.0;
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'total', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];

        $this->pdf->draw($ary, $data);
        $this->pdf->draw($lab, $label);

        return $total;
    }

    private function drawPlus($year, $last_year = null)
    {
        $data  = ['dummy' => null];
        $ary   = [];
        $total = 0;

        $start = "$year-01-01 00:00:00";
        $end   = "$year-12-31 23:59:59";

        $sql = "SELECT item_code_left AS code,
                       SUM(amount_left) AS amount  
                  FROM `table::transfer`
                 WHERE category = 'A'
                   AND (issue_date >= ? AND issue_date <= ?)
                 GROUP BY item_code_left";
        if (false === $this->db->query($sql, [$start, $end])) {
            return false;
        }

        $products = $this->filter_items['PRODUCTS'];
        $purchase = $this->filter_items['PURCHASE'];
        $beginning_inventory = $this->filter_items['BEGINNING_INVENTORY'];
        $periodend_inventory = $this->filter_items['PERIODEND_INVENTORY'];
        $investments = $this->filter_items['INVESTMENTS'];
        $lost_fixedasset = $this->filter_items['LOST_FIXEDASSET'];
        $lumpsum_depreciable_assets = $this->filter_items['LUMPSUM_DEPRECIABLE_ASSETS'];

        while ($unit = $this->db->fetch()) {
            if (isset($this->financial_converter[$unit['code']])) {
                $unit['code'] = $this->financial_converter[$unit['code']];
            }
            if ($unit['code'] == $products) {
                continue;
            }
            if ($unit['code'] == $purchase) {
                $unit['code'] = $periodend_inventory;
            }
            if (!isset($data[$unit['code']]))  {
                $data[$unit['code']] = 0;
            }
            $data[$unit['code']] += $unit['amount'];
            $total += $unit['amount'];
        }
        if (!is_null($last_year)) {
            $start = "{$last_year}-01-01 00:00:00";
            $end   = "{$last_year}-12-31 23:59:59";
            $sql = "SELECT item_code_left AS code,
                           SUM(amount_left) AS amount  
                      FROM `table::transfer`
                     WHERE item_code_left IN (?, ?, ?)
                       AND (issue_date >= ? AND issue_date <= ?)
                     GROUP BY item_code_left";
            if (false === $this->db->query($sql, [$investments, $periodend_inventory, $lost_fixedasset, $start, $end])) {
                return false;
            }
            while ($unit = $this->db->fetch()) {
                if (!isset($data[$unit['code']]))  {
                    $data[$unit['code']] = 0;
                }
                $data[$unit['code']] += $unit['amount'];
                $total += $unit['amount'];
            }

            $lost = (isset($data[$lost_fixedasset])) ? (int)$data[$lost_fixedasset] : 0;
            $data[$investments] = (int)$data[$investments] + $lost;
            $this->investments = $data[$investments];
        }
        $w = 30;
        $h = 6.34;
        $y = 37;
        $shift = (is_null($last_year)) ? 0 : 1;
        $x = 48 + $w * $shift;


        $keys = $this->oas_config->creditor_keys;

        foreach ($keys as $key) {
            $label_key = "{$key}_label";
            if (isset($data[$key])) {
                $data[$key] = number_format($data[$key]);
            }
            $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => $key, 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true];

            if ($key === $lumpsum_depreciable_assets) {
                $ary[] = ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => $label_key, 'suffix' => '', 'x' => 21.0, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'J', 'flg' => true];
                $data[$label_key] = $this->db->get('item_name', 'account_items', 'item_code=?', array($key));
            }

            $y += $h;
        }
        $data['total'] = number_format($total);
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'total', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true];

        $this->pdf->draw($ary, $data);

        return true;
    }

    private function drawMinus($year, $last_year = null)
    {
        $total = 0;
        $ary  = [];
        $data = ['dummy' => null];

        $start = "{$year}-01-01 00:00:00";
        $end   = "{$year}-12-31 23:59:59";
        $sql = "SELECT item_code_right AS code,
                       SUM(amount_right) AS amount  
                  FROM `table::transfer`
                 WHERE category = 'A' AND item_code_right <> ?
                   AND (issue_date >= ? AND issue_date <= ?)
                 GROUP BY item_code_right";

        $deposit = $this->filter_items['DEPOSIT'];
        $withdrawals = $this->filter_items['WITHDRAWALS'];
        $gain_on_sale_of_fixedassets = $this->filter_items['GAIN_ON_SALE_OF_FIXEDASSETS'];

        if (false === $this->db->query($sql, [$this->filter_items['BEGINNING_INVENTORY'], $start, $end])) {
            return false;
        }
        while ($unit = $this->db->fetch()) {
            if (!isset($data[$unit['code']]))  {
                $data[$unit['code']] = 0;
            }
            $data[$unit['code']] += $unit['amount'];
            if (!is_null($last_year) && $unit['code'] === $deposit) {
                continue;
            }
            $total += $unit['amount'];
        }
        if (!is_null($last_year)) {
            $start = "{$last_year}-01-01 00:00:00";
            $end   = "{$last_year}-12-31 23:59:59";
            $sql = "SELECT item_code_right AS code,
                           SUM(amount_right) AS amount  
                      FROM `table::transfer`
                     WHERE item_code_right IN (?, ?) 
                       AND (issue_date >= ? AND issue_date <= ?)
                     GROUP BY item_code_right";
            if (false === $this->db->query($sql, [$withdrawals, $gain_on_sale_of_fixedassets, $start, $end])) {
                return false;
            }
            while ($unit = $this->db->fetch()) {
                if ($unit['code'] === $gain_on_sale_of_fixedassets) {
                    $unit['code'] = $withdrawals;
                }
                if (!isset($data[$unit['code']]))  {
                    $data[$unit['code']] = 0;
                }
                $data[$unit['code']] += $unit['amount'];
                $total += $unit['amount'];
            }
            $data['no33'] = $this->column33;
            $data['no43'] = $this->column43;
            $total += $data['no43'];
            $data[$deposit] = $this->capital;
            $total += $data[$deposit];
            //
            $kari = (isset($data[$withdrawals])) ? (int)$data[$withdrawals] : 0;
            $this->capital = $kari + (int)$data[$deposit] + (int)$data['no33'] - $this->investments;
        } else {
            $data['no43'] = null;
            $this->capital = $data[$deposit];
        }

        $w = 30;
        $h = 6.34;
        $y = 37;
        $shift = (is_null($last_year)) ? 0 : 1;
        $x = 139 + $w * $shift;

        $keys = $this->oas_config->debit_keys;

        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $data[$key] = number_format($data[$key]);
            }
            $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => $key, 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true];
            $y += $h;
        }
        $data['total'] = number_format($total);
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'total', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true];

        $this->pdf->draw($ary, $data);

        return true;
    }

    private function drawDeduction(): void
    {
        $data = array(
            'no06'  => 0,
            'no07'  => 0,
            'no08'  => 0,
            'no09'  => 0,
            'no08a' => 0,
            'no09a' => 0 
        );

        $data['no09'] = number_format(min(self::BASIC_DEDUCTION - (int)$data['no08'], $this->column43));
        $data['no07'] = number_format($this->column43);

        $ary = [
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' =>  'no06', 'suffix' => '', 'x' => 230, 'y' => 158.0, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' =>  'no07', 'suffix' => '', 'x' => 230, 'y' => 164.0, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' =>  'no08', 'suffix' => '', 'x' => 230, 'y' => 170.3, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' =>  'no09', 'suffix' => '', 'x' => 230, 'y' => 176.6, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'no08a', 'suffix' => '', 'x' => 230, 'y' => 183.0, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'no09a', 'suffix' => '', 'x' => 230, 'y' => 189.3, 'type' => 'Cell', 'width' => 44.7, 'height' => 6.16, 'align' => 'R', 'flg' => true],
        ];

        $this->pdf->draw($ary, $data);
    } 
}
