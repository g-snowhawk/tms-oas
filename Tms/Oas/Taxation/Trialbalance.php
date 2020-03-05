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
class Trialbalance extends \Tms\Oas\Taxation
{
    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => Lang::translate('HEADER_TITLE'), 'id' => 'osa-taxation-trialblance', 'class' => 'taxation']
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

        $template_path = 'oas/taxation/trialbalance.tpl';
        $html_id = 'oas-taxation-trialbalance';

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
        $target_year = strtotime($this->app->request->param('nendo') . '-01-01');

        $this->pdf->loadTemplate("oas/taxation/trialbalance.pdf");
        $tplIdx = $this->pdf->addPageFromTemplate(1); 

        $sql = function($column, $this_year) {
            $category = ($this_year) ? " AND category <> 'A'" : '';
            $versus = ($column === 'left') ? 'right' : 'left';

            $category = "";
            $subquery = "SELECT SUM(amount_{$column}) AS amount_{$column},
                                MIN(item_code_{$column}) AS item_code,
                                MIN(item_code_{$versus}) AS rev_code
                           FROM `table::transfer`
                          WHERE userkey = ?{$category}
                            AND (issue_date >= ? AND issue_date <= ?)
                          GROUP BY item_code_{$column}";

            return "SELECT t.amount_{$column},
                           t.rev_code,
                           a.item_code,
                           a.item_name,
                           a.alias,
                           a.note
                      FROM ({$subquery}) t
                     INNER JOIN `table::account_items` a
                             ON t.item_code = a.item_code
                     ORDER BY a.item_code";
        };

        $this_year = (date('Y') === date('Y', $target_year));
        $date = ($this_year) ? date('m-d') : '12-31';

        $start = date("Y-01-01 00:00:00", $target_year);
        $end   = date("Y-{$date} 23:59:59", $target_year);

        $replaces = [$this->uid, $start, $end];

        $purchase = 0;
        $purchaseCode = $this->filter_items['PURCHASE'];
        $beginningInventory = $this->filter_items['BEGINNING_INVENTORY'];
        $periodEndInventory = $this->filter_items['PERIODEND_INVENTORY'];

        if (!$this->db->query($sql('left', $this_year), $replaces)) {
            trigger_error($this->db->error());
            return;
        }
        while ($result = $this->app->db->fetch()) {
            $item_code = $result['item_code'];
            if ($item_code === $beginningInventory) {
                continue;
            }
            if (!isset($items[$item_code])) {
                $items[$item_code] = array('amount_left' => 0, 'amount_right' => 0);
            }
            $items[$item_code]['item_name'] = ($item_code === $periodEndInventory) ? $result['alias'] : $result['item_name'];
            $items[$item_code]['amount_left'] += $result['amount_left'];
            $items[$item_code]['note'] = $result['note'];
        }

        if (!$this->app->db->query($sql('right', $this_year), $replaces)) {
            trigger_error($this->db->error());
            return;
        }
        while ($result = $this->app->db->fetch()) {
            $item_code = $result['item_code'];
            if ($item_code === $beginningInventory) {
                $item_code = $periodEndInventory;
                continue;
            }
            if (!isset($items[$item_code])) {
                $items[$item_code] = array('amount_left' => 0, 'amount_right' => 0);
            }
            if ($item_code === $purchaseCode && $result['rev_code'] === $periodEndInventory) {
                $items[$item_code]['item_name'] = $result['item_name'];
                $items[$item_code]['amount_left'] += $purchase - $result['amount_right'];
                continue;
            }
            $items[$item_code]['item_name'] = ($item_code === $periodEndInventory) ? $result['alias'] : $result['item_name'];
            $items[$item_code]['amount_right'] += $result['amount_right'];
            $items[$item_code]['note'] = $result['note'];
        }
        ksort($items);

        $item_name = '';
        $y = 44;
        $total = array(
            'item_name'     => Lang::translate('TB_TOTAL'),
            'amount_left'   => 0,
            'amount_right'  => 0,
            'balance_left'  => 0,
            'balance_right' => 0,
        );

        foreach ($items as $key => $item) {
            if (!empty($item_name)) {
                $balance = $data['amount_left'] - $data['amount_right'];
                if ($balance > 0) {
                    $total['balance_left'] += abs($balance);
                } else {
                    $total['balance_right'] += abs($balance);
                }
                if ($data['amount_left']   == 0) $data['amount_left']   = '';
                if ($data['amount_right']  == 0) $data['amount_right']  = '';
                if ($data['balance_left']  == 0) $data['balance_left']  = '';
                if ($data['balance_right'] == 0) $data['balance_right'] = '';

                $map = [
                    ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'item_name',     'suffix' => '', 'x' =>  20.0, 'y' => $y, 'type' => 'Cell', 'width' => 40, 'height' => 8, 'align' => 'L', 'flg' => true],
                    ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',   'suffix' => '', 'x' =>  62.7, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                    ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right',  'suffix' => '', 'x' =>  94.2, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                    ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance_left',  'suffix' => '', 'x' => 125.7, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                    ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'balance_right', 'suffix' => '', 'x' => 157.2, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                ];

                $this->pdf->draw($map, $data);
                $y += 8;
            }
            $data = array(
                'amount_left'   => 0,
                'amount_right'  => 0,
                'balance_left'  => 0,
                'balance_right' => 0,
            );
            $data['item_name'] = $item['item_name'];
            if (!empty($item['note'])) {
                $data['item_name'] .= ' (' . $item['note'] . ')';
            }
            if (isset($item['amount_left'])) {
                $data['amount_left'] += $item['amount_left'];
                $total['amount_left'] += $item['amount_left'];
            }
            if (isset($item['amount_right'])) {
                $data['amount_right'] += $item['amount_right'];
                $total['amount_right'] += $item['amount_right'];
            }
            $balance = $data['amount_left'] - $data['amount_right'];
            if ($balance > 0) {
                $data['balance_left'] = abs($balance);
            } else {
                $data['balance_right'] = abs($balance);
            }
            $item_name = $data['item_name'];
        }
        $balance = $data['amount_left'] - $data['amount_right'];
        if ($balance > 0) {
            $total['balance_left'] += abs($balance);
        } else {
            $total['balance_right'] += abs($balance);
        }
        if ($data['amount_left']   == 0) { $data['amount_left']   = ''; }
        if ($data['amount_right']  == 0) { $data['amount_right']  = ''; }
        if ($data['balance_left']  == 0) { $data['balance_left']  = ''; }
        if ($data['balance_right'] == 0) { $data['balance_right'] = ''; }
        $this->pdf->draw($map, $data, $y);
        $y += 8;

        $this->pdf->draw($map, $total, $y);

        $offset_left  = $total['amount_left'] - $total['amount_right'];
        $offset_right = $total['balance_left'] - $total['balance_right'];
        if ($offset_left !== 0 || $offset_right !== 0) {
            $y += 8;
            $map = [
                ['font' => $this->mono, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], '', 'name' => 'amount_left',   '', 'x' =>  62.7, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                ['font' => $this->mono, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], '', 'name' => 'amount_right',  '', 'x' =>  94.2, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                ['font' => $this->mono, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], '', 'name' => 'balance_left',  '', 'x' => 125.7, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
                ['font' => $this->mono, 'style' => '', 'size' => 9, 'color' => [255, 0, 0], '', 'name' => 'balance_right', '', 'x' => 157.2, 'y' => $y, 'type' => 'Cell', 'width' => 33, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.65],
            ];
            $offsets['amount_left']   = ($offset_left  > 0) ? '' : $offset_left;
            $offsets['amount_right']  = ($offset_left  > 0) ? $offset_left : '';
            $offsets['balance_left']  = ($offset_right > 0) ? '' : $offset_right;
            $offsets['balance_right'] = ($offset_right > 0) ? $offset_right : '';
            $this->pdf->draw($map, $offsets, $y);
        }

        $this->pdf->output('trialbalance.pdf');
    }
}
