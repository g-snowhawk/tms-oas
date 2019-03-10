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
        $tYear = $this->app->request->POST('nendo') . '-01-01';

        $this->pdf->loadTemplate("oas/taxation/trialbalance.pdf");
        $tplIdx = $this->pdf->addPageFromTemplate(1); 

        $sql = $this->SQL('left', $tYear);
        if (!$this->db->query($sql)) {
            trigger_error($this->db->error());
            return;
        }

        $purchase = 0;
        $purchaseCode = $this->filter_items['PURCHASE'];
        $beginningInventory = $this->filter_items['BEGINNING_INVENTORY'];
        $periodEndInventory = $this->filter_items['PERIODEND_INVENTORY'];

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
        }
        $sql = $this->SQL('right', $tYear);
        if (!$this->app->db->query($sql)) {
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
            if ($item['item_name'] != $item_name) {
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
            }
            $data['item_name'] = $item['item_name'];
            if (array_key_exists('amount_left', $item)) {
                $data['amount_left'] += $item['amount_left'];
                $total['amount_left'] += $item['amount_left'];
            }
            if (array_key_exists('amount_right', $item)) {
                $data['amount_right'] += $item['amount_right'];
                $total['amount_right'] += $item['amount_right'];
            }
            $balance = $data['amount_left'] - $data['amount_right'];
            if ($balance > 0) {
                $data['balance_left'] = abs($balance);
            } else {
                $data['balance_right'] = abs($balance);
            }
            $item_name = $item['item_name'];
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

    public function SQL()
    {
        $args = func_get_args();
        $lod = filter_var($args[0], FILTER_SANITIZE_STRING);
        $at = filter_var($args[1], FILTER_SANITIZE_STRING);

        if ($at) {
            $start = date("Y-01-01 00:00:00", strtotime($at));
            $end   = date("Y-12-31 23:59:59", strtotime($at));
            $where = " AND (issue_date >= " . $this->db->quote($start) .
                     " AND issue_date <= " . $this->db->quote($end) . ")";
        }
        $rev = ($lod === 'left') ? 'right' : 'left';
        return "SELECT SUM(td.amount_$lod) AS amount_$lod,
                       MIN(td.item_code_{$rev}) AS rev_code,
                       MIN(ai.item_name) AS item_name,
                       MIN(ai.alias) AS alias,
                       MIN(ai.item_code) AS item_code
                  FROM `" . $this->db->TABLE('transfer') . "` td
                  JOIN `" . $this->db->TABLE('account_items') . "` ai
                    ON td.item_code_$lod = ai.item_code
                 WHERE td.userkey=" . $this->db->quote($this->uid) . "
                       $where
              GROUP BY ai.item_code
              ORDER BY ai.item_code";
    }
}
