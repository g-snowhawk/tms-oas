<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Oas\Fixedasset;

use P5\Lang;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Oas\Fixedasset
{
    const MEMVALUE = 1;
    const LINE_HEIGHT = 8;

    private $y = 78;
    private $pages = 0;

    private $closed = false;

    private $acquire_quantity_total = 0;
    private $acquire_price_total    = 0;
    private $depreciate_price_total = 0;
    private $change_quantity_total  = 0;
    private $change_price_total     = 0;
    private $quantity_onhand_total  = 0;
    private $price_onhand_total     = 0;

    private $pMon = 1;
    private $pDay = 1;

    private $lumpsum_depreciable_assets;

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            [
                'title' => \P5\Lang::translate('HEADER_TITLE'),
                'id' => 'osa-fixedasset',
                'class' => 'fixedasset'
            ]
        );

        $this->lumpsum_depreciable_assets = $this->db->get(
            'item_code',
            'account_items',
            'userkey = ? AND system_operator = ?',
            [$this->uid, 'LUMPSUM_DEPRECIABLE_ASSETS']
        );
    }

    /**
     * Default view.
     */
    public function defaultView() : void
    {
        $this->checkPermission('oas.fixedasset.read');

        $template_path = 'oas/fixedasset/default.tpl';
        $html_id = 'oas-fixedasset-default';

        //if ($this->request->method === 'post') {
        //    $this->edit(true);
        //}

        $items = $this->db->select('*', 'fixed_assets', 'WHERE userkey = ?', [$this->uid]);
        $this->view->bind('items', $items);

        $form_params = $this->view->param('form');
        if (is_null($form_params)) {
            $form_params = [];
        }
        $form_params['target'] = 'TmsPDFWindow';
        $this->view->bind('form', $form_params);

        $this->setHtmlId($html_id);
        $this->view->render($template_path);
    }

    /**
     * Edit view.
     */
    public function edit($readonly = false) : void
    {
        $this->checkPermission('oas.fixedasset.update');

        if ($this->request->method === 'post') {
            $post = $this->request->post();
        } else {
            $post = $this->db->get(
                '*', 'fixed_assets', 'id = ?', [$this->request->param('id')]
            );
        }
        $this->view->bind('post', $post);

        $sql = "SELECT ai.item_code,ai.item_name
                  FROM `table::account_items` ai
                  JOIN (SELECT group_d
                          FROM `table::account_group_d` ad
                          JOIN `table::account_group_c` ac
                            ON ad.group_c = ac.group_c
                         WHERE ac.group_b = '2100') acd
                    ON ai.group_d = acd.group_d WHERE userkey = ?";

        if (false !== $this->db->query($sql, [$this->uid])) {
            $items = [];
            while ($unit = $this->db->fetch()) {
                $items[$unit['item_code']] = $unit['item_name'];
            }
            $this->view->bind('items', $items);
        }

        $template_path = 'oas/fixedasset/edit.tpl';
        $html_id = 'oas-fixedasset-edit';

        $this->setHtmlId($html_id);
        $this->view->render($template_path);
    }

    public function pdf()
    {
        $tYear = $this->request->POST('nendo');

        $this->pdf->loadTemplate("oas/fixedasset/default.pdf");

        if (!$this->db->query($this->SQL(null), [$this->uid])) {
            return false;
        }

        $over = [];
        $this->pages = 0;
        $results = $this->db->fetchAll();
        foreach ($results as $result) {
            if (!isset($over[$result['item']])) $over[$result['item']] = 0;
            $this->acquire_quantity_total = 0;
            $this->acquire_price_total    = 0;
            $this->depreciate_price_total = 0;
            $this->change_quantity_total  = 0;
            $this->change_price_total     = 0;
            $this->quantity_onhand_total  = 0;
            $this->price_onhand_total     = 0;

            $this->closed = false;

            ++$this->pages;
            $this->pdf->addPageFromTemplate(1, 'L');

            $result['page'] = $this->pages;
            $result['year'] = $this->toWareki("{$tYear}-01-01", true);
            $result['acqr'] = $this->getWareki($result['acquire']);

            $ary = [
                ['font' => $this->mincho, 'style' => '', 'size' => 11, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'title',           'suffix' => '', 'x' => 108, 'y' => 20, 'type' => 'Cell', 'width' => 80, 'height' => 6, 'align' => 'C', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'id',              'suffix' => '', 'x' =>  22, 'y' => 37, 'type' => 'Cell', 'width' => 40, 'height' => 6, 'align' => 'C', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'item_name',       'suffix' => '', 'x' =>  22, 'y' => 45, 'type' => 'Cell', 'width' => 40, 'height' => 6, 'align' => 'C', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'acqr',            'suffix' => '', 'x' => 178, 'y' => 37, 'type' => 'Cell', 'width' => 38, 'height' => 6, 'align' => 'C', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'location',        'suffix' => '', 'x' => 178, 'y' => 45, 'type' => 'Cell', 'width' => 38, 'height' => 6, 'align' => 'C', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'durability',      'suffix' => '', 'x' => 178, 'y' => 53, 'type' => 'Cell', 'width' => 38, 'height' => 6, 'align' => 'C', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'depreciate_type', 'suffix' => '', 'x' => 248, 'y' => 37, 'type' => 'Cell', 'width' => 38, 'height' => 6, 'align' => 'C', 'flg' => true],
                ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'depreciate_rate', 'suffix' => '', 'x' => 248, 'y' => 45, 'type' => 'Cell', 'width' => 38, 'height' => 6, 'align' => 'C', 'flg' => true],
                ['font' => $this->gothic, 'style' => '', 'size' =>  9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'year',            'suffix' => '', 'x' => 9.5, 'y' => 68, 'type' => 'Cell', 'width' =>  9, 'height' => 6, 'align' => 'R', 'flg' => true],
            ];
            $this->pdf->draw($ary, $result);

            $this->y = 78;
            $ary = $this->pdfMappingRow();

            if ($tYear === date('Y', strtotime($result['acquire']))) {
                $data = [
                    'month'   => date('n', strtotime($result['acquire'])),
                    'date'    => date('j', strtotime($result['acquire'])),
                    'summary' => Lang::translate('ACQUIRE'),
                    'acquire_quantity' => $result['quantity'],
                ];
            } else {
                $data = [
                    'month'   => 1,
                    'date'    => 1,
                    'summary' => Lang::translate('FROM_PRIV_YEAR'),
                    'acquire_quantity' => $result['quantity'],
                ];
            }

            if ($data['acquire_quantity'] > 1) {
                $data['acquire_unit']  = number_format($result['price']);
            }
            $data['acquire_price'] = $result['quantity'] * $result['price'];
            $s = date("Y", strtotime($result['acquire']));
            $data['depreciate_price'] = $this->depreciate($s, $tYear - 1, $result);

            if ($s === $tYear) {
                $data['depreciate_price'] = 0;
            }

            $data['quantity_onhand'] = $data['acquire_quantity'];
            $price_onhand = $data['acquire_price'] - $data['depreciate_price'];
            $data['price_onhand'] = $price_onhand;

            $this->acquire_quantity_total += $data['acquire_quantity'];
            $this->acquire_price_total    += $data['acquire_price'];
            $this->depreciate_price_total += $data['depreciate_price'];
            $this->quantity_onhand_total  += $data['quantity_onhand'];
            $this->price_onhand_total     += $data['price_onhand'];

            $this->pdf->draw($ary, $data, $this->y);
            $this->y += self::LINE_HEIGHT;
            $this->pMon = $data['month'];
            $this->pDay = $data['date'];

            //
            $this->drawChange($result, $price_onhand, $tYear);

            // kimatsu
            if ($this->closed !== true && $tYear >= date('Y')) {
                continue;
            }
            if ($this->closed == false) {
                $this->drawClosing($result, $price_onhand, $tYear);
            }

            $style = [
                'width' => 0.25,
                'cap' => 'butt',
                'join' => 'miter',
                'color' => [0, 0, 0]
            ];
            $y1 = $this->y + 1;
            $y2 = $y1 + 6;
            $line_map = [
                ['name' => 'line', 'x' => 59, 'y' => $y1, 'x2' =>  23, 'y2' => $y2, 'type' => 'Line', 'style' => $style],
                ['name' => 'line', 'x' => 23, 'y' => $y2, 'x2' => 287, 'y2' => $y2, 'type' => 'Line', 'style' => $style],
            ];
            $this->pdf->draw($line_map, ['line' => 2]);

            $this->y += self::LINE_HEIGHT;

            $data = [
                'summary'  => Lang::translate('TB_TOTAL'),
            ];
            if ($this->acquire_quantity_total != 0) $data['acquire_quantity'] = $this->acquire_quantity_total;
            if ($this->acquire_price_total    != 0) $data['acquire_price']    = $this->acquire_price_total;
            if ($this->depreciate_price_total != 0) $data['depreciate_price'] = $this->depreciate_price_total;
            if ($this->change_quantity_total  != 0) $data['change_quantity']  = $this->change_quantity_total;
            if ($this->change_price_total     != 0) $data['change_price']     = $this->change_price_total;
            $this->quantity_onhand_total = $this->acquire_quantity_total - $this->change_quantity_total;
            $this->price_onhand_total = $this->acquire_price_total - ($this->depreciate_price_total + abs($this->change_price_total));
            if ($this->closed !== true && $this->price_onhand_total <= 0) {
                $this->price_onhand_total = ($result['item'] !== $this->lumpsum_depreciable_assets)
                    ? self::MEMVALUE : 0;
            }
            if ($this->quantity_onhand_total  > 0) {
                $data['quantity_onhand']  = $this->quantity_onhand_total;
            }
            $data['price_onhand'] = $this->price_onhand_total;
            $this->pdf->draw($ary, $data, $this->y);
            $over[$result['item']] += $this->price_onhand_total;
        }

        $year = date('Y', strtotime("{$tYear}-01-01"));
        $file = $this->getPdfPath($year, 'taxation', 'fixedassets.pdf');
        $locked = ($this->request->POST('locked') === '1') ? true : false;
        $this->outputPdf(basename($file), dirname($file), true, $locked);
    }

    /**
     * Draw change
     *
     * @param number $y
     * @return void
     */
    public function drawChange(&$result, &$price_onhand, $year)
    {
        $sql = "SELECT * 
                  FROM `" . $this->db->TABLE('fixed_assets_detail') . "`
                 WHERE id = " . $this->db->quote($result['id']) . "
                   AND year = " . $this->db->quote($year);
        if (!$this->db->query($sql)) {
            return;
        }
        $lines = [];
        while ($unit = $this->db->fetch()) {
            if ($unit['summary'] === Lang::translate('SOLD')) {
                $single = round($result['price'] * $result['depreciate_rate'] / 12);
                $s = $single * $unit['month'];

                if ($price_onhand >= $s) {
                    $price_onhand -= $s;
                } else {
                    $s = 0;
                }

                $data = [
                    'month' => $unit['month'],
                    'date' => $unit['date'],
                    'summary' => Lang::translate('DEPRECIATION'),
                    'depreciate_price' => $s,
                    'price_onhand' => $price_onhand,
                    'official_ratio' => $result['official_ratio'] . '%',
                    'note' => number_format($s)
                ];
                $this->depreciate_price_total += $s;
                $ary = $this->pdfMappingRow('R');
                $this->pdf->draw($ary, $data, $this->y);
                $this->y += self::LINE_HEIGHT;

                $note = Lang::translate('BRACKETS_LEFT') . Lang::translate('SALE_PRICE') . number_format($unit['change_price']) . Lang::translate('COMMA');
                $eki = $unit['change_price'] - $price_onhand;
                $note .= (($eki < 0) ? Lang::translate('LOSS_ON_SALE') : Lang::translate('GAIN_ON_SALE')) . number_format($eki) . Lang::translate('BRACKETS_RIGHT');
                $data = [
                    'date' => Lang::translate('IDENTICAL'),
                    'summary' => $unit['summary'], 
                    'change_quantity' => $unit['change_quantity'],
                    'change_price' => -$price_onhand,
                    'price_onhand' => 0,
                    'note' => $note
                ];
                $ary = $this->pdfMappingRow('C');
                $this->pdf->draw($ary, $data, $this->y);
                $this->y += self::LINE_HEIGHT;

                $this->change_quantity_total += $unit['change_quantity'];
                $this->change_price_total    += -$price_onhand;
                $price_onhand = 0;
                $this->price_onhand_total = $price_onhand;
                $this->closed = true;
            }
            elseif ($unit['summary'] === Lang::translate('RETIREMENT')) {
                $single = round($result['price'] * $result['depreciate_rate'] / 12);
                $s = $single * $unit['month'];

                if ($price_onhand >= $s) {
                    $price_onhand -= $s;
                } else {
                    $s = 0;
                }

                $data = [
                    'month' => $unit['month'],
                    'date' => $unit['date'],
                    'summary' => Lang::translate('DEPRECIATION'),
                    'depreciate_price' => $s,
                    'price_onhand' => $price_onhand,
                    'official_ratio' => $result['official_ratio'] . '%',
                    'note' => number_format($s)
                ];
                $this->depreciate_price_total += $s;
                $ary = $this->pdfMappingRow('R');
                $this->pdf->draw($ary, $data, $this->y);
                $this->y += self::LINE_HEIGHT;

                $note = Lang::translate('BRACKETS_LEFT') . Lang::translate('LOSS_OF_SALE') . number_format($unit['change_price']) . Lang::translate('YEN') . Lang::translate('BRACKETS_RIGHT');
                $data = [
                    'date' => Lang::translate('IDENTICAL'),
                    'summary' => $unit['summary'], 
                    'change_quantity' => $unit['change_quantity'],
                    'change_price' => -$price_onhand,
                    'price_onhand' => 0,
                    'note' => $note
                ];
                $ary = $this->pdfMappingRow('C');
                $this->pdf->draw($ary, $data, $this->y);
                $this->y += self::LINE_HEIGHT;

                $this->change_quantity_total += $unit['change_quantity'];
                $this->change_price_total    += -$price_onhand;
                $price_onhand = 0;
                $this->price_onhand_total = $price_onhand;
                $this->closed = true;
            }
        }

        if (empty($lines)) {
            return;
        }

        foreach ($lines as $data) {
            if ($data['month'] == $this->pMon) {
                $data['month'] = '';
            } else {
                $this->pMon = $data['month'];
            }
            if ($data['date']  == $this->pDay) {
                $data['date']  = Lang::translate('IDENTICAL');
                $da = 'C';
            } else {
                $this->pDay = $data['date'];
                $da = 'R';
            }
            $ary = $this->pdfMappingRow($da);
            $this->pdf->draw($ary, $data, $this->y);
            $this->y += self::LINE_HEIGHT;
        }
    }

    /**
     * Draw kimatsu
     *
     * @return void
     */
    public function drawClosing(&$result, &$price_onhand, $year, $month = 12, $date = 31)
    {
        $data = [
            'month'    => $month,
            'date'     => $date,
            'summary'  => Lang::translate('DEPRECIATION'),
        ];
        if ($data['month'] == $this->pMon) {
            $data['month'] = '';
        } else {
            $this->pMon = $data['month'];
        }
        if ($data['date']  == $this->pDay) {
            $data['date']  = Lang::translate('IDENTICAL');
            $da = 'C';
        } else {
            $this->pDay = $data['date'];
            $da = 'R';
        }
        $ary = $this->pdfMappingRow($da);
        $tp = $this->depreciate($year, $year, $result);
        if ($tp > $price_onhand) {
            $data['depreciate_price'] = $price_onhand;
            $memValue = self::MEMVALUE;
            $data['price_onhand'] = $memValue;
        } else {
            $data['depreciate_price'] = $tp;
            $memValue = 0;
            $data['price_onhand'] = $price_onhand - $data['depreciate_price'];
        }
        $data['official_ratio'] = $result['official_ratio'] . '%';

        $inclusion_amount = $data['depreciate_price'] - $memValue;
        if ($inclusion_amount > 0) {
            $data['note'] = number_format($inclusion_amount);
        }

        $this->depreciate_price_total += $data['depreciate_price'];
        $this->price_onhand_total     += $data['price_onhand'];

        $this->pdf->draw($ary, $data, $this->y);
        $this->y += self::LINE_HEIGHT;
    }

    /**
     * Wareki
     *
     * @param  string   date
     * @return string
     */
    public function getWareki($date)
    {
        $y = $this->toWareki($date);
        $n = date('n', strtotime($date));
        $j = date('j', strtotime($date));
        return sprintf(Lang::translate('DATE_FORMAT_H'), $y, $n, $j);
    }

    public function pdfMappingRow($align = 'R')
    {
        return [
            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',            'suffix' => '', 'x' =>    10, 'y' => 0, 'type' => 'Cell', 'width' =>   7, 'height' => 8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'date',             'suffix' => '', 'x' =>    17, 'y' => 0, 'type' => 'Cell', 'width' => 5.5, 'height' => 8, 'align' => $align, 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',          'suffix' => '', 'x' =>    22, 'y' => 0, 'type' => 'Cell', 'width' =>  38, 'height' => 8, 'align' => 'L', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'acquire_quantity', 'suffix' => '', 'x' =>    60, 'y' => 0, 'type' => 'Cell', 'width' =>  15, 'height' => 8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'acquire_unit',     'suffix' => '', 'x' =>    75, 'y' => 0, 'type' => 'Cell', 'width' =>  15, 'height' => 8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'acquire_price',    'suffix' => '', 'x' =>  89.5, 'y' => 0, 'type' =>  'Tri', 'width' =>  24, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.2],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'depreciate_price', 'suffix' => '', 'x' => 117.5, 'y' => 0, 'type' =>  'Tri', 'width' =>  24, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.2],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'change_quantity',  'suffix' => '', 'x' =>   146, 'y' => 0, 'type' => 'Cell', 'width' =>  15, 'height' => 8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'change_price',     'suffix' => '', 'x' => 160.5, 'y' => 0, 'type' =>  'Tri', 'width' =>  24, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.2],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'quantity_onhand',  'suffix' => '', 'x' =>   189, 'y' => 0, 'type' => 'Cell', 'width' =>  15, 'height' => 8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'price_onhand',     'suffix' => '', 'x' => 203.5, 'y' => 0, 'type' =>  'Tri', 'width' =>  24, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.2],
            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'official_ratio',   'suffix' => '', 'x' =>   232, 'y' => 0, 'type' => 'Cell', 'width' =>  14, 'height' => 8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'note',             'suffix' => '', 'x' =>   247, 'y' => 0, 'type' => 'Cell', 'width' =>  40, 'height' => 8, 'align' => 'L', 'flg' => true],
        ];
    }

    public function depreciate($startYear, $endYear, $result)
    {
        $total = 0;
        $acquire = date("Y", strtotime($result['acquire']));
        $limit = $acquire + $result['durability'];

        if ($result['item'] === $this->lumpsum_depreciable_assets) {
            $tYear = $this->request->POST('nendo');
            if ($startYear + 3 < $tYear) {
                return 0;
            }
            $surplus = $result['price'] % 3;
            $total = floor($result['price'] / 3);
            if ($startYear === $tYear) {
                return $total + $surplus;
            }

            return $total * ($tYear - $startYear) + $surplus;
        }

        for ($y = $startYear; $y <= $endYear; $y++) {
            if (isset($result['ido']['month'])) {
                $months = $result['ido']['month'];
            } else if ($y == $acquire) {
                $months = 13 - date("n", strtotime($result['acquire']));
            } else if ($y == $limit) {
                $months = 12 - (13 - date("n", strtotime($result['acquire'])));
                $y = $endYear;
            } else {
                $months = ($y > $limit) ? 0 : 12;
            }
            $total += ceil($result['price'] * $result['depreciate_rate'] * ($months / 12));
        }
        if ($total >= $result['price']) {
            $total = $result['price'] - self::MEMVALUE;
        }
        return $total;
    }

    public function SQL()
    {
        $args = func_get_args();
        $lod = filter_var($args[0], FILTER_SANITIZE_STRING);
        if (isset($args[1])) {
            $at = filter_var($args[1], FILTER_SANITIZE_STRING);
        }

        return "SELECT fa.*,
                       ai.item_name
                  FROM `table::fixed_assets` fa
                  JOIN `table::account_items` ai
                    ON fa.item = ai.item_code
                 WHERE fa.userkey = ? AND fa.quantity > 0";
    }
}
