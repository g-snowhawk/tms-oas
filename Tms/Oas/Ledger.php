<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2020 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Oas;

use DateTime;
use P5\Lang;
use \Tms\Oas\Taxation;
use Tms\Pdf;

/**
 * Category management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Ledger extends Taxation
{
    /**
     * Using common accessor methods
     */
    use \Tms\Accessor;

    const LINE_HEIGHT = 8.3;

    private $pages = 0;
    private $lines = 30;

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => Lang::translate('HEADER_TITLE'), 'id' => 'osa-ledger', 'class' => 'ledger']
        );

        $paths = $this->view->getPaths();
        $this->pdf = new Pdf($paths);
    }

    /**
     * Default view.
     */
    public function defaultView() : void
    {
        $this->checkPermission('oas.ledger.read');

        $template_path = 'oas/ledger.tpl';
        $html_id = 'oas-ledger';

        $form_params = $this->view->param('form');
        if (is_null($form_params)) {
            $form_params = [];
        }
        $form_params['target'] = 'TmsPDFWindow';
        $this->view->bind('form', $form_params);

        $this->setHtmlId($html_id);
        $this->view->render($template_path);
    }

    public function pdf() 
    {
        $tYear = $this->request->POST('nendo') . '-01-01';
        $nYear = $this->request->POST('nendo') + 1;

        $current_year = date('Y-01-01');
        $during = ($tYear === $current_year);

        $this->pdf->loadTemplate("oas/ledger.pdf");

        $sql = "SELECT * FROM `table::account_items`";

        if (!$this->db->query($sql)) {
            return false;
        }

        while ($result = $this->db->fetch()) {
            $items[$result['item_code']] = $result;
        }
        ksort($items);

        foreach ($items as $key => $item) {

            if ($key < 1000) {
                continue;
            }

            $this->pages = 1;
            $lineNo  = 0;
            $balance = 0;
            $total_left  = 0;
            $total_right = 0;
            $m_total_left = 0;
            $m_total_right = 0;
            $month   = 0;
            $day     = 0;
            $lod     = '';
            $y       = 38;

            if ((string)$key === $this->filter_items['BEGINNING_INVENTORY']) {
                continue;
            }

            $dayAlign = 'L';

            $cln = clone $this->db;
            $sql = $this->SQL($key, $tYear);
            $recordCount = $cln->recordCount($sql);
            if ($cln->query($sql)) {
                while ($result = $cln->fetch()) {
                    $k = ($result['item_code_right'] == $key) ? 'right' : 'left';
                    $u = ($k == 'left') ? 'right' : 'left';
                    $b = ($k == 'left') ? -1 : 1;
                    if (empty($result["amount_$k"])) {
                        continue;
                    }

                    if ($lineNo >= ($this->lines - 1)) {
                        $fw = [
                            'day'     => Lang::translate('IDENTICAL'),
                            'summary' => Lang::translate('TO_NEXT_PAGE'),
                            'balance' => abs($balance),
                            'lod'     => Lang::translate('IDENTICAL')
                        ];
                        $ary = [
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',     'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',     'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance', 'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                        ];
                        $this->pdf->draw($ary, $fw);
                        $this->pages++;
                        $lineNo = 0;
                        $y = 38;
                    }

                    if ($lineNo === 0) {
                        $this->pdf->addPageFromTemplate();
                        $head = [
                            'num'     => $this->pages,
                            'year'    => $this->toWareki($tYear, true),
                            'summary' => $this->pageTitle($key, $item)
                        ];
                        $ary = [
                            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'num',     'suffix' => '', 'x' => 183, 'y' =>    9, 'type' => 'Cell', 'width' => 17, 'height' => 6, 'align' => 'R', 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 11, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>  65, 'y' =>   19, 'type' => 'Cell', 'width' => 80, 'height' => 7, 'align' => 'C', 'flg' => true],
                            ['font' => $this->gothic, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'year',    'suffix' => '', 'x' =>  10, 'y' => 28.7, 'type' => 'Cell', 'width' => 12, 'height' => 6, 'align' => 'R', 'flg' => true],
                        ];
                        $this->pdf->draw($ary, $head);

                        if ($this->pages > 1) {
                            $lod = ($balance <= 0) ? Lang::translate('DEBT') : Lang::translate('LOAN');
                            $fw = [
                                'month'   => $month,
                                'day'     => $day,
                                'summary' => Lang::translate('FROM_PRIV_PAGE'),
                                'balance' => abs($balance),
                                'lod'     => $lod
                            ];
                            $ary = [
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',   'suffix' => '', 'x' =>     10, 'y' => $y, 'type' => 'Cell', 'width' =>  10, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',     'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',     'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance', 'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                            ];
                            $this->pdf->draw($ary, $fw);
                            $y += self::LINE_HEIGHT;
                            $lineNo++;
                        }
                    }

                    $m = date("n", strtotime($result['issue_date']));
                    $d = date("j", strtotime($result['issue_date']));

                    $data = [];
                    if ($m === $month) {
                        $data['month'] = '';
                        if ($d === $day) {
                            $data['day'] = Lang::translate('IDENTICAL');
                            $dayAlign = "C";
                        } else {
                            $data['day'] = $d;
                            $dayAlign = "R";
                        }
                    } else {

                        $data['month'] = $m;
                        $data['day']   = $d;
                        $dayAlign = "R";

                        // Forwarding
                        if ($m > 1 && $balance !== 0) {
                            if ($lineNo >= ($this->lines - 3)) {
                                while($lineNo < $this->lines - 1) {
                                    $y += self::LINE_HEIGHT;
                                    $lineNo++;
                                }
                                $fw = [
                                    'day'     => Lang::translate('IDENTICAL'),
                                    'summary' => Lang::translate('TO_NEXT_PAGE'),
                                    'balance' => abs($balance),
                                    'lod'     => Lang::translate('IDENTICAL')
                                ];
                                $ary = [
                                    ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',     'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell',  'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                                    ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell',  'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                                    ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',     'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell',  'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                                    ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance', 'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell',  'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                                ];
                                $this->pdf->draw($ary, $fw);
                                ++$this->pages;
                                $lineNo = 0;
                                $y = 38;

                                $this->pdf->addPageFromTemplate();
                                $head = [
                                    'num'     => $this->pages,
                                    'year'    => $this->toWareki($tYear, true),
                                    'summary' => $this->pageTitle($key, $item)
                                ];
                                $ary = [
                                    ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'num',     'suffix' => '', 'x' => 183, 'y' =>    9, 'type' => 'Cell', 'width' => 17, 'height' => 6, 'align' => 'R', 'flg' => true],
                                    ['font' => $this->mincho, 'style' => '', 'size' => 11, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>  65, 'y' =>   19, 'type' => 'Cell', 'width' => 80, 'height' => 7, 'align' => 'C', 'flg' => true],
                                    ['font' => $this->gothic, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'year',    'suffix' => '', 'x' =>  10, 'y' => 28.7, 'type' => 'Cell', 'width' => 12, 'height' => 6, 'align' => 'R', 'flg' => true],
                                ];
                                $this->pdf->draw($ary, $head);

                                if ($this->pages > 1) {
                                    $lod = ($balance <= 0) ? Lang::translate('DEBT') : Lang::translate('LOAN');
                                    $fw = [
                                        'month'   => $month,
                                        'day'     => $day,
                                        'summary' => Lang::translate('FROM_PRIV_PAGE'),
                                        'balance' => abs($balance),
                                        'lod'     => $lod
                                    ];
                                    $ary = [
                                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',   'suffix' => '', 'x' =>     10, 'y' => $y, 'type' => 'Cell', 'width' =>  10, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',     'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',     'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                                        ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance', 'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                                    ];
                                    $this->pdf->draw($ary, $fw);
                                    $y += self::LINE_HEIGHT;
                                    $lineNo++;
                                }
                            }

                            $fw = [];
                            $fw['day'] = date('t', strtotime($this->request->POST('nendo') . "-{$month}-01"));

                            // 
                            if (strtotime($this->request->POST('nendo') . "-{$month}-{$fw['day']}") > time()) {
                                continue;
                            }

                            $fw['summary'] = Lang::translate('LG_NEXT');
                            $fwKey = ($balance > 0) ? 'amount_left' : 'amount_right';
                            $fw[$fwKey] = abs($balance);
                            //
                            if ($balance < 0) {
                                $m_total_right += abs($balance);
                            } else {
                                $m_total_left += abs($balance);
                            }
                            $ary = [
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'day',          'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                            ];
                            $this->pdf->draw($ary, $fw);
                            $y += self::LINE_HEIGHT;
                            $lineNo++;

                            // Draw separator
                            $this->singleLineShort($y);
 
                            $fw = [
                                'summary' => "({$month}" . Lang::translate('LG_MONTH') . ")",
                                'amount_left' => $m_total_left,
                                'amount_right' => $m_total_right
                            ];
                            $ary = [
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' => 47, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                            ];
                            $this->pdf->draw($ary, $fw);
                            $y += self::LINE_HEIGHT;
                            $lineNo++;
                            $total_left += $m_total_left;
                            $total_right += $m_total_right;
                            $m_total_left = 0;
                            $m_total_right = 0;

                            // Draw separator
                            $this->doubleLineLong($y);

                            // Fill monthes
                            if ($item['show_empty'] === '1' && $month > 0 && $m - $month > 1) {
                                $this->fillMonths(
                                    $month + 1, $m - 1, $balance, $lod, $dayAlign,
                                    $m_total_left, $m_total_right, $total_left, $total_right,
                                    $y, $lineNo
                                );
                            }

                            $data['month'] = null;
                            $fw = ['month' => $m];
                            $fw['day'] = '1';
                            $fw['summary'] = Lang::translate('LG_PREV');
                            $fwKey = ($balance < 0) ? 'amount_left' : 'amount_right';
                            $fw[$fwKey] = abs($balance);
                            $fw['lod'] = $lod;
                            $fw['balance'] = abs($balance);
                            //
                            if ($balance > 0) {
                                $m_total_right += abs($balance);
                            } else {
                                $m_total_left += abs($balance);
                            }
                            $ary = [
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',        'suffix' => '', 'x' =>     10, 'y' => $y, 'type' => 'Cell', 'width' =>  10, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',          'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',          'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance',      'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                            ];
                            $this->pdf->draw($ary, $fw);
                            $y += self::LINE_HEIGHT;
                            $lineNo++;
                        }
                    }

                    if ($lineNo == ($this->lines - 1)) {
                        $fw = [
                            'day'     => Lang::translate('IDENTICAL'),
                            'summary' => Lang::translate('TO_NEXT_PAGE'),
                            'balance' => abs($balance),
                            'lod'     => Lang::translate('IDENTICAL')
                        ];
                        $ary = [
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',     'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',     'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance', 'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                        ];
                        $this->pdf->draw($ary, $fw);
                        ++$this->pages;
                        $lineNo = 0;
                        $y = 38;

                        $this->pdf->addPageFromTemplate();
                        $head = [
                            'num'     => $this->pages,
                            'year'    => $this->toWareki($tYear, true),
                            'summary' => $this->pageTitle($key, $item)
                        ];
                        $ary = [
                            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'num',     'suffix' => '', 'x' => 183, 'y' =>    9, 'type' => 'Cell', 'width' => 17, 'height' => 6, 'align' => 'R', 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 11, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>  65, 'y' =>   19, 'type' => 'Cell', 'width' => 80, 'height' => 7, 'align' => 'C', 'flg' => true],
                            ['font' => $this->gothic, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'year',    'suffix' => '', 'x' =>  10, 'y' => 28.7, 'type' => 'Cell', 'width' => 12, 'height' => 6, 'align' => 'R', 'flg' => true],
                        ];
                        $this->pdf->draw($ary, $head);

                        if ($this->pages > 1) {
                            $lod = ($balance <= 0) ? Lang::translate('DEBT') : Lang::translate('LOAN');
                            $fw = [
                                'month'   => $m,
                                'day'     => '1',
                                'summary' => Lang::translate('FROM_PRIV_PAGE'),
                                'balance' => abs($balance),
                                'lod'     => $lod
                            ];
                            $ary = [
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',   'suffix' => '', 'x' =>     10, 'y' => $y, 'type' => 'Cell', 'width' =>  10, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',     'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',     'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance', 'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                            ];
                            $this->pdf->draw($ary, $fw);
                            $y += self::LINE_HEIGHT;
                            $lineNo++;
                        }
                    }

                    $month = $m;
                    $day   = $d;

                    $balance += $result["amount_$k"] * $b;

                    if ($result['category'] === 'A') {
                        $data['summary'] = $this->summary($key, $result, $items, $u, $k);
                        if (in_array($key, $this->operation_filter)) {
                            if ((int)$result["item_code_$u"] === $key) {
                                $data["amount_$u"] = $result["amount_$u"];
                            }
                            if ((int)$result["item_code_$k"] === $key) {
                                $data["amount_$k"] = $result["amount_$k"];
                            }
                        }
                        if ($balance < 0) {
                            $m_total_left += abs($balance);
                        } else {
                            $m_total_right += abs($balance);
                        }
                    } else if ($result['category'] === 'Z') {
                        $data['summary'] = $this->summary($key, $result, $items, $u, $k);
                        if ($balance < 0) {
                            $m_total_right += $result['amount_right'];
                            $data['amount_right'] = $result['amount_right'];
                        } else {
                            $m_total_left += $result['amount_left'];
                            $data['amount_left'] = $result['amount_left'];
                        }
                    } else {
                        $data['summary'] = $this->summary($key, $result, $items, $u, $k);
                        $data["amount_$k"] = $result["amount_$k"];
                        $data['page']      = $result['page_number'];
                        if (isset($data['amount_left'])) {
                            $m_total_left += $data['amount_left'];
                        }
                        if (isset($data['amount_right'])) {
                            $m_total_right += $data['amount_right'];
                        }
                    }

                    $data['balance'] = abs($balance);
                    $l = ($balance <= 0) ? Lang::translate('DEBT') : Lang::translate('LOAN');
                    $data['lod'] = ($l == $lod) ? Lang::translate('IDENTICAL') : $l;
                    $lod = $l;

                    $ary = [
                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',        'suffix' => '', 'x' =>     10, 'y' => $y, 'type' => 'Cell', 'width' =>  10, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',          'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'page',         'suffix' => '', 'x' =>     73, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                        ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                        ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',          'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                        ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance',      'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                    ];
                    $this->pdf->draw($ary, $data);
                    $y += self::LINE_HEIGHT;
                    $lineNo++;
                }

                // Forwarding
                if ($balance !== 0) {
                    if ($lineNo >= ($this->lines - 3)) {
                        while($lineNo < $this->lines - 1) {
                            $y += self::LINE_HEIGHT;
                            $lineNo++;
                        }
                        $fw = [
                            'day'     => Lang::translate('IDENTICAL'),
                            'summary' => Lang::translate('TO_NEXT_PAGE'),
                            'balance' => abs($balance),
                            'lod'     => Lang::translate('IDENTICAL')
                        ];
                        $ary = [
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'nema' => 'day',     'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'nema' => 'summary', 'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'nema' => 'lod',     'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'nema' => 'balance', 'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                        ];
                        $this->pdf->draw($ary, $fw);
                        ++$this->pages;
                        $lineNo = 0;
                        $y = 38;

                        $this->pdf->addPageFromTemplate();
                        $head = [
                            'num'     => $this->pages,
                            'year'    => $this->toWareki($tYear, true),
                            'summary' => $this->pageTitle($key, $item)
                        ];
                        $ary = [
                            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'num',     'suffix' => '', 'x' => 183, 'y' =>    9, 'type' => 'Cell', 'width' => 17, 'height' => 6, 'align' => 'R', 'flg' => true],
                            ['font' => $this->mincho, 'style' => '', 'size' => 11, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>  65, 'y' =>   19, 'type' => 'Cell', 'width' => 80, 'height' => 7, 'align' => 'C', 'flg' => true],
                            ['font' => $this->gothic, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'year',    'suffix' => '', 'x' =>  10, 'y' => 28.7, 'type' => 'Cell', 'width' => 12, 'height' => 6, 'align' => 'R', 'flg' => true],
                        ];
                        $this->pdf->draw($ary, $head);

                        if ($this->pages > 1) {
                            $lod = ($balance <= 0) ? Lang::translate('DEBT') : Lang::translate('LOAN');
                            $fw = [
                                'month'   => $month,
                                'day'     => $day,
                                'summary' => Lang::translate('FROM_PRIV_PAGE'),
                                'balance' => abs($balance),
                                'lod'     => $lod
                            ];
                            $ary = [
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',   'suffix' => '', 'x' =>     10, 'y' => $y, 'type' => 'Cell', 'width' =>  10, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',     'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary', 'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',     'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance', 'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                            ];
                            $this->pdf->draw($ary, $fw);
                            $y += self::LINE_HEIGHT;
                            $lineNo++;
                        }
                    }

                    $fw = [];
                    $fw['day'] = date('t', strtotime($this->request->POST('nendo') . "-{$month}-01"));
                    $fw['summary'] = ($key > 8000 || in_array($key, [1129,1139,1891,4891,7111])) ? Lang::translate('LG_THIS_STAGE') : Lang::translate('LG_NEXT_STAGE');

                    // 
                    if (strtotime($this->request->POST('nendo') . "-{$month}-{$fw['day']}") > time()) {
                        continue;
                    }

                    if ($during) {
                        $fw['summary'] = Lang::translate('LG_NEXT');
                    }

                    $fwKey = ($balance > 0) ? 'amount_left' : 'amount_right';
                    $fw[$fwKey] = abs($balance);
                    //
                    if ($balance < 0) {
                        $m_total_right += abs($balance);
                    } else {
                        $m_total_left += abs($balance);
                    }
                    $ary = [
                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'day',          'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                        ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                        ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                    ];
                    $this->pdf->draw($ary, $fw);
                    $y += self::LINE_HEIGHT;
                    $lineNo++;

                    // Draw separator
                    $this->singleLineShort($y);

                    $fw = [
                        'summary' => "({$month}" . Lang::translate('LG_MONTH') . ")",
                        'amount_left' => $m_total_left,
                        'amount_right' => $m_total_right
                    ];
                    $ary = [
                        ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' => 47, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                        ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                        ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                    ];
                    $this->pdf->draw($ary, $fw);
                    $y += self::LINE_HEIGHT;
                    $lineNo++;
                    $total_left += $m_total_left;
                    $total_right += $m_total_right;
                    $m_total_left = 0;
                    $m_total_right = 0;

                    // Draw separator
                    $this->doubleLineLong($y);

                    $data['month'] = null;

                    // Fill monthes
                    if ($item['show_empty'] === '1' && $month > 0) {
                        $end = date('n');
                        $this->fillMonths(
                            $month + 1, $end, $balance, $lod, $dayAlign,
                            $m_total_left, $m_total_right, $total_left, $total_right,
                            $y, $lineNo
                        );
                    }

                }

                if (in_array($key, $this->filter_items['FORWARD_1'])) {
                    $this->transferAmount(date('Y', strtotime($tYear)), $key, $balance);
                }
            } else {
                echo $this->db->error();
                exit;
            }
        }

        $year = date('Y', strtotime($tYear));
        $file = $this->getPdfPath($year, 'taxation', 'ledger.pdf');
        $locked = ($this->request->POST('locked') === '1') ? true : false;
        $this->outputPdf(basename($file), dirname($file), true, $locked);
    }

    public function SQL(): String
    {
        $args = func_get_args();
        $item_code = filter_var($args[0], FILTER_SANITIZE_STRING);
        $at = filter_var($args[1], FILTER_SANITIZE_STRING);

        if ($at) {
            $start = date("Y-01-01 00:00:00", strtotime($at));
            $end   = date("Y-12-31 23:59:59", strtotime($at));
            $where = " AND issue_date >= " . $this->db->quote($start) .
                     " AND issue_date <= " . $this->db->quote($end);
        }

        $beginningInventory = $this->filter_items['BEGINNING_INVENTORY'];
        $periodendInventory = $this->filter_items['PERIODEND_INVENTORY'];

        $sub_where = "item_code_left=" . $this->db->quote($item_code) . "
                     OR item_code_right=" . $this->db->quote($item_code);
        if ((string)$item_code === $periodendInventory) {
            $sub_where .= " OR item_code_left='{$beginningInventory}' OR item_code_right='{$beginningInventory}'";
        }
        $where .= " AND ($sub_where)";

        return "SELECT issue_date, page_number, userkey, category, line_number,
                       CASE WHEN item_code_left = '{$beginningInventory}' THEN '{$periodendInventory}'
                            ELSE item_code_left 
                        END AS item_code_left, 
                       amount_left, summary, amount_right, 
                       CASE WHEN item_code_right = '{$beginningInventory}' THEN '{$periodendInventory}'
                            ELSE item_code_right 
                        END AS item_code_right, 
                       CASE WHEN category = 'A' OR category = 'Z' THEN category
                            ELSE 'B'
                        END AS sorter, 
                       note, trade
                  FROM `table::transfer` 
                 WHERE userkey=" . $this->db->quote($this->uid) . "
                       $where
              ORDER BY issue_date, sorter, page_number, line_number";
    }

    /**
     * Drawing fixed addets detail
     * 
     * @param string $at
     * @return number
     */
    //public function sumFixedassets($at): Int
    //{
    //    $sql = "SELECT * FROM `table::fixed_assets`"; 
    //    if (!$this->db->query($sql)) {
    //        return 0;
    //    }

    //    $total = 0;
    //    $dpsum = 0;
    //    $spsum = 0;
    //    $ohsum = 0;
    //    $tysum = 0;
    //    while ($result = $this->db->fetch()) {
    //        $t = date("Y", strtotime($at));
    //        $s = date("Y", strtotime($result['acquire']));
    //        if ($t > $s + $result['durability']) {
    //            continue;
    //        }
    //        $depreciate_price = Tms_Pms_Accounting_Fixedasset_Pdf::depreciate($s, $t - 1, $result);
    //        $price_onhand = ($result['quantity'] * $result['price']) - $depreciate_price;
    //        $tp = Tms_Pms_Accounting_Fixedasset_Pdf::depreciate($t, $t, $result);
    //        if ($tp > $price_onhand) {
    //            $depreciate_price = $price_onhand;
    //            $memValue = 1;
    //        } else {
    //            $depreciate_price = $tp;
    //            $memValue = 0;
    //        }
    //        $depreciate = $depreciate_price - $memValue;
    //        $price_onhand -= $depreciate;
    //        $ohsum += $price_onhand;
    //        $special = 0;
    //        $total += $depreciate - $special;
    //        $tysum += ($depreciate - $special) * ($result['official_ratio'] / 100);
    //        $limit = $s + $result['durability'];
    //        if ($t == $s) {
    //            $months = 13 - date("n", strtotime($result['acquire']));
    //        } else if ($t == $limit) {
    //            $months = 12 - (13 - date("n", strtotime($result['acquire'])));
    //        } else {
    //            $months = 12;
    //        }
    //    }
    //    return $total;
    //}

    /**
     * Summary String
     * 
     * @param int $key
     * @param array $result
     * @param string $u
     * @param string $k
     * @return string
     */
    public function summary($key, array $result, $items, $u, $k) : string
    {
        $col = ($key === $this->filter_items['PURCHASE']) ? 'alias' : 'item_name';
        $value = (isset($items[$result["item_code_$u"]])) ? $items[$result["item_code_$u"]][$col] : '';
        $summary = (!empty($result["amount_$u"]) && $result["amount_$k"] > $result["amount_$u"])
                 ? Lang::translate('SHOKUCHI') : $value;
        if (!empty($result['summary'])) {
            $summary .= ' ' . $result['summary'];
        }
        return $summary;
    }

    public function pageTitle($key, array $item) : string
    {
        $summary = ($key === $this->filter_items['PERIODEND_INVENTORY']) ? $item['alias'] : $item['item_name'];
        if (!empty($item['note']) && $key !== $this->filter_items['PERIODEND_INVENTORY']) {
            $summary .= Lang::translate('BRACKETS_LEFT') . $item['note'] . Lang::translate('BRACKETS_RIGHT');
        }
        return $summary;
    }

    private function singleLineShort($y) : void
    {
        $style = [
            'width' => 0.5,
            'cap' => 'butt',
            'join' => 'miter',
            'color' => [255, 0, 0],
        ];
        $fw = [
            'line' => 1,
        ];
        $ary = [
            ['name' => 'line', 'x' => 79, 'y' => $y, 'x2' => 153, 'y2' => $y, 'type' => 'Line', 'style' => $style],
        ];
        $this->pdf->draw($ary, $fw);
    }

    private function doubleLineLong($y1) : void
    {
        $style = [
            'width' => 0.25,
            'cap' => 'butt',
            'join' => 'miter',
            'color' => [255, 0, 0],
        ];
        $fw = [
            'line' => 2,
        ];
        $y2 = $y1 - 0.75;
        $ary = [
            ['name' => 'line', 'x' => 79, 'y' => $y1, 'x2' => 200, 'y2' => $y1, 'type' => 'Line', 'style' => $style],
            ['name' => 'line', 'x' => 79, 'y' => $y2, 'x2' => 200, 'y2' => $y2, 'type' => 'Line', 'style' => $style],
        ];
        $this->pdf->draw($ary, $fw);
    }

    private function fillMonths($start, $end, $balance, $lod, $dayAlign, &$m_total_left, &$m_total_right, &$total_left, &$total_right, &$y, &$lineNo)
    {
        for ($i = $start; $i <= $end; $i++) {
            $fw = [
                'month' => $i,
                'day' => '1',
                'summary' => Lang::translate('LG_PREV'),
            ];
            $fwKey = ($balance < 0) ? 'amount_left' : 'amount_right';
            $fw[$fwKey] = abs($balance);
            $fw['lod'] = $lod;
            $fw['balance'] = abs($balance);
            //
            if ($balance > 0) {
                $m_total_right += abs($balance);
            } else {
                $m_total_left += abs($balance);
            }
            $ary = [
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',        'suffix' => '', 'x' =>     10, 'y' => $y, 'type' => 'Cell', 'width' =>  10, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',          'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'lod',          'suffix' => '', 'x' =>    153, 'y' => $y, 'type' => 'Cell', 'width' =>   6, 'height' => self::LINE_HEIGHT, 'align' => 'C', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance',      'suffix' => '', 'x' => 157.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
            ];
            $this->pdf->draw($ary, $fw);
            $y += self::LINE_HEIGHT;
            $lineNo++;

            $fw = [];
            $fw['day'] = date('t', strtotime($this->request->POST('nendo') . "-{$i}-01"));

            // 
            if (strtotime($this->request->POST('nendo') . "-{$month}-{$fw['day']}") > time()) {
                continue;
            }

            $fw['summary'] = Lang::translate('LG_NEXT');

            $this_month = (int)date('n');
            $today = (int)date('j');
            if ($i === $this_month && (int)$fw['day'] > $today) {
                continue;
            }

            $fwKey = ($balance > 0) ? 'amount_left' : 'amount_right';
            $fw[$fwKey] = abs($balance);
            //
            if ($balance < 0) {
                $m_total_right += abs($balance);
            } else {
                $m_total_left += abs($balance);
            }
            $ary = [
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'day',          'suffix' => '', 'x' =>     20, 'y' => $y, 'type' => 'Cell', 'width' => 6.2, 'height' => self::LINE_HEIGHT, 'align' => $dayAlign, 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' =>  47, 'height' => self::LINE_HEIGHT, 'align' => 'L', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [255, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' =>  33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
            ];
            $this->pdf->draw($ary, $fw);
            $y += self::LINE_HEIGHT;
            $lineNo++;

            // Draw separator
            $this->singleLineShort($y);

            $fw = [
                'summary' => "({$i}" . Lang::translate('LG_MONTH') . ")",
                'amount_left' => $m_total_left,
                'amount_right' => $m_total_right
            ];
            $ary = [
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>     26, 'y' => $y, 'type' => 'Cell', 'width' => 47, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  77.75, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 114.75, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => self::LINE_HEIGHT, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
            ];
            $this->pdf->draw($ary, $fw);
            $y += self::LINE_HEIGHT;
            $lineNo++;
            $total_left += $m_total_left;
            $total_right += $m_total_right;

            // Draw separator
            $this->doubleLineLong($y);
        }
    }
}
