<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Oas\Transfer;

use DateTime;
use Tms\Pdf;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Oas\Transfer
{
    const LEAVES = 3;

    private $pages = 0;
    private $lines = 0;

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => \P5\Lang::translate('HEADER_TITLE'), 'id' => 'osa-transfer', 'class' => 'transfer']
        );

        $paths = $this->view->getPaths();
        $this->pdf = new Pdf($paths);
    }

    /**
     * Default view.
     */
    public function defaultView() : void
    {
        $this->checkPermission('oas.transfer.read');

        $template_path = 'oas/transfer/default.tpl';
        $html_id = 'oas-transfer-default';

        if ($this->request->method === 'post') {
            $this->edit(true);
        }

        $new_category = $this->request->param('t');
        if (!empty($new_category)) {
            $this->session->param('transfer_category', $new_category);
            $this->edit(true);
        }

        $this->setHtmlId($html_id);
        $this->view->render($template_path);
    }

    /**
     * Edit view.
     */
    public function edit($readonly = false) : void
    {
        $id = $this->request->param('id');
        $privilege_type = (empty($id)) ? 'create' : 'update';

        $this->checkPermission('oas.transfer.'.$privilege_type);

        $category = $this->session->param('transfer_category');
        if (empty($category) || !array_key_exists($category, parent::LINE_COUNT)) {
            $category = 'T';
        }

        if (false === $readonly && $this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $post = [];
            if ($this->request->param('add') === '1') {
                $post['addnew'] = '1';
                if ($this->request->param('issue_date')) {
                    $post['issue_date'] = $this->request->param('issue_date');
                }
            } else {
                $latest_transfer = $this->latestTransfer($category, 'issue_date,page_number');
                $first_transfer = $this->firstTransfer($category, 'issue_date,page_number');

                if ($this->request->param('cur')) {
                    $date = new DateTime($this->request->param('cur'));
                    if ($this->request->param('p')) {
                        $current_transfer = [
                            'issue_date' => $date->format('Y-m-d'),
                            'page_number' => $this->request->param('p')
                        ];
                    } else {
                        $current_transfer = $this->transferByDay(
                            $date->format('Y-m-d'), $category, 'issue_date,page_number'
                        );
                    }
                }
                if (empty($current_transfer)) {
                    $current_transfer = ($this->request->param('atfirst')) ? $first_transfer : $latest_transfer;
                }

                $next_transfer = $this->nextTransfer($current_transfer['issue_date'], $current_transfer['page_number'], $category, 'MIN(issue_date) AS issue_date,MIN(page_number) AS page_number');
                $this->view->bind('nextPage', $next_transfer);

                $previous_transfer = $this->previousTransfer($current_transfer['issue_date'], $current_transfer['page_number'], $category, 'MIN(issue_date) AS issue_date,MIN(page_number) AS page_number');
                $this->view->bind('prevPage', $previous_transfer);

                $fetch = $this->currentTransfer($current_transfer['issue_date'], $current_transfer['page_number'], $category);
                if (empty($fetch)) {
                    $readonly = false;
                    $post['addnew'] = '1';
                    if ($this->request->param('issue_date')) {
                        $post['issue_date'] = $this->request->param('issue_date');
                    }
                } else {
                    foreach ($fetch as $unit) {
                        $line_number = $unit['line_number'];
                        foreach ($unit as $key => $value) {
                            if (!in_array($key, ['category','issue_date','page_number','trade','locked'])) {
                                if (!isset($post[$key])) {
                                    $post[$key] = [];
                                }
                                $post[$key][$line_number] = $unit[$key];
                            } elseif (!isset($post[$key])) {
                                $post[$key] = $value;
                            }
                        }
                    }
                }
            }
        }
        $post['category'] = $category;
        $this->view->bind('post', $post);

        $this->view->bind('readonly', $readonly);

        $account_items = $this->db->select(
            'item_code,item_name,alias,note', parent::ACCOUNT_ITEM_TABLE,
            'WHERE userkey = ? ORDER BY item_code', [$this->uid]
        );
        $this->view->bind('accountItems', $account_items);

        $this->view->bind('lineCount', parent::LINE_COUNT[$post['category']]);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        if ($readonly) {
            $form['class'] = ['readonly'];
        }
        $this->view->bind('form', $form);

        $header = $globals['header'];
        $header['id'] = 'oas-transfer-edit';
        $this->view->bind('header', $header);

        $this->view->bind('err', $this->app->err);
        $this->view->render('oas/transfer/edit.tpl');
    }

    public function calendar()
    {
        $date = new DateTime($this->request->param('date'));
        $month = $date->format('Y-m');
        $firstDay = new DateTime("first day of $month");
        $lastDay = new DateTime("last day of $month");

        $json_array = [
            'date' => $date->format('Y-m-d'),
        ];

        $category = $this->session->param('transfer_category');
        $this->db->query(
            "SELECT DATE_FORMAT(issue_date, '%e') AS day
               FROM table::transfer
              WHERE userkey = ? AND category = ? AND issue_date >= ? AND issue_date <= ?
              GROUP BY issue_date",
            [$this->uid, $category, $firstDay->format('Y-m-d'), $lastDay->format('Y-m-d')]
        );
        $result = $this->db->fetchAll();
        if (!empty($result)) {
            $json_array['days'] = [];
            foreach ((array)$result as $unit) {
                $json_array['days'][] = $unit['day'];
            }
        }

        header('Content-type: application/json');
        echo json_encode($json_array);
        exit;
    }

    public function pdf()
    {
        $locked = ($this->request->param('locked') === '1');
        $category = $this->request->param('category');
        $begin = date("Y-m-d 00:00:00", strtotime($this->request->param('begin')));
        $end   = date("Y-m-d 23:59:59", strtotime($this->request->param('end')));
        if ($locked) {
            $begin = date("Y-01-01 00:00:00", strtotime($begin));
            $end   = date("Y-12-31 23:59:59", strtotime($begin));
        }
        $closure = function($col) {
            return "SELECT lf.*, ai.item_name AS item_{$col}
                      FROM (SELECT * FROM `table::transfer`
                             WHERE userkey = ? AND category = ?
                               AND (issue_date >= ? AND issue_date <= ?)
                           ) lf
                      LEFT JOIN `table::account_items` ai
                        ON lf.item_code_{$col} = ai.item_code";
        };

        $column = ($category === 'R') ? 'right' : 'left';
        $header_map = [
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'page_number', 'suffix' => '', 'x' => 54, 'y' =>  9.5, 'type' => 'Cell', 'width' => 16, 'height' =>  6, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'year',        'suffix' => '', 'x' => 24, 'y' => 20.3, 'type' => 'Cell', 'width' => 12, 'height' =>  6, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',       'suffix' => '', 'x' => 39, 'y' => 20.3, 'type' => 'Cell', 'width' => 12, 'height' =>  6, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',         'suffix' => '', 'x' => 54, 'y' => 20.3, 'type' => 'Cell', 'width' => 12, 'height' =>  6, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'trade',       'suffix' => '', 'x' => 67, 'y' => 26.0, 'type' => 'Cell', 'width' => 68, 'height' => 15, 'align' => 'L', 'flg' => true],
        ];
        $body_map = [
            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => "item_{$column}",   'suffix' => '', 'x' =>    22, 'y' => 32, 'type' => 'Cell', 'width' => 25, 'height' => 8, 'align' => 'C', 'flg' => true, 'pitch' => 0],
            ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',          'suffix' => '', 'x' =>    47, 'y' => 32, 'type' => 'Cell', 'width' => 64, 'height' => 8, 'align' => 'C', 'flg' => true, 'pitch' => 0],
            ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => "amount_{$column}", 'suffix' => '', 'x' => 110.5, 'y' => 32, 'type' => 'Cell', 'width' => 26, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.15],
        ];
        $footer_map = [
            ['font' => $this->mono, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => "amount_{$column}",  'suffix' => '', 'x' => 110.5, 'y' => 32, 'type' => 'Cell', 'width' => 26, 'height' => 8, 'align' => 'R', 'flg' => true, 'pitch' => 1.15],
        ];
        $this->lines = 4;
        $line_height = 8;
        switch ($category) {
        case 'P':
            $template = 'payment.pdf';
            $sql = $closure($column);
            break;
        case 'R':
            $template = 'receipt.pdf';
            $sql = $closure($column);
            break;
        default:
            $template = 'transfer.pdf';
            $this->lines = 7;
            $line_height = 7;
            $sql = "SELECT lf.*, ar.item_name AS item_right
                      FROM `table::account_items` ar
                     RIGHT JOIN (SELECT td.*, ai.item_name AS item_left
                                   FROM `table::transfer` td
                                   LEFT JOIN `table::account_items` ai
                                     ON td.item_code_left = ai.item_code
                                  WHERE td.userkey = ? AND td.category = ?
                                    AND (td.issue_date >= ? AND td.issue_date <= ?)
                                ) lf
                        ON lf.item_code_right = ar.item_code";
            $header_map = [
                ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'page_number', 'suffix' => '', 'x' => 109, 'y' =>  9.5, 'type' => 'Cell', 'width' => 16, 'height' => 6, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'year',        'suffix' => '', 'x' =>  24, 'y' => 20.3, 'type' => 'Cell', 'width' => 12, 'height' => 6, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'month',       'suffix' => '', 'x' =>  39, 'y' => 20.3, 'type' => 'Cell', 'width' => 12, 'height' => 6, 'align' => 'R', 'flg' => true],
                ['font' => $this->mono, 'style' => '', 'size' => 10, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'day',         'suffix' => '', 'x' =>  54, 'y' => 20.3, 'type' => 'Cell', 'width' => 12, 'height' => 6, 'align' => 'R', 'flg' => true],
            ];
            $body_map = [
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' => '', 'x' =>  20.5, 'y' => 32, 'type' => 'Cell', 'width' => 26, 'height' => 7, 'align' => 'R', 'flg' => true, 'pitch' => 1.15],
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'item_left',    'suffix' => '', 'x' =>    52, 'y' => 32, 'type' => 'Cell', 'width' => 25, 'height' => 7, 'align' => 'C', 'flg' => true, 'pitch' => 0],
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'summary',      'suffix' => '', 'x' =>    77, 'y' => 32, 'type' => 'Cell', 'width' => 64, 'height' => 7, 'align' => 'C', 'flg' => true, 'pitch' => 0],
                ['font' => $this->mincho, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'item_right',   'suffix' => '', 'x' =>   141, 'y' => 32, 'type' => 'Cell', 'width' => 25, 'height' => 7, 'align' => 'C', 'flg' => true, 'pitch' => 0],
                ['font' => $this->mono,   'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' => '', 'x' => 164.5, 'y' => 32, 'type' => 'Cell', 'width' => 26, 'height' => 7, 'align' => 'R', 'flg' => true, 'pitch' => 1.15],
            ];
            $footer_map = [
                ['font' => $this->mono, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_left',  'suffix' =>  '', 'x' =>  20.5, 'y' => 32, 'type' => 'Cell', 'width' => 26, 'height' => 7, 'align' => 'R', 'flg' => true, 'pitch' => 1.15],
                ['font' => $this->mono, 'style' => '', 'size' => 9, 'color' => [0, 0, 0], 'prefix' => '', 'name' => 'amount_right', 'suffix' =>  '', 'x' => 164.5, 'y' => 32, 'type' => 'Cell', 'width' => 26, 'height' => 7, 'align' => 'R', 'flg' => true, 'pitch' => 1.15],
            ];
            break;
        }

        $replaces = [$this->uid, $category, $begin, $end];

        $max = 0;
        if ($this->db->query(
            preg_replace(
                "/^SELECT.+?FROM/is",
                "SELECT MAX(lf.page_number) as max FROM",
                $sql,
                1
            ), $replaces
        )) {
            $result = $this->db->fetch();
            $max = $result['max'];
        } else {
            echo $this->db->error();
            exit;
        }

        $sql .= " WHERE lf.page_number = ?";

        $this->pdf->loadTemplate("oas/transfer/{$template}");

        $this->pages = 1;
        for ($n = 0; $n < $max; $n++) {
            if ($n % self::LEAVES === 0) {
                ++$this->pages;
                $tplIdx = $this->pdf->addPageFromTemplate(1); 
                $shiftY = 0;
            }
            $num = $n + 1;
            $replaces[4] = $num;
            if ($this->db->query($sql, $replaces)) {
                $results = $this->db->fetchAll();
                $unit = [];
                foreach ($results as $result) {
                    $unit[$result['line_number']] = $result;
                }
                $totalLeft  = 0;
                $totalRight = 0;
                $forceY = ($category === 'T') ? 32 : 47;
                for ($i = 1; $i <= $this->lines; $i++) {
                    if (!isset($unit[$i])) {
                        $forceY += $line_height;
                        continue;
                    }
                    $data = $unit[$i];
                    if ($i == 1) {
                        $data['year']  = date('Y', strtotime($data['issue_date']));
                        $data['month'] = date('n', strtotime($data['issue_date']));
                        $data['day']   = date('j', strtotime($data['issue_date']));
                        $this->pdf->draw($header_map, $data, null, $shiftY);
                    }
                    if (empty($data['amount_left'])) {
                        $data['item_left'] = null;
                    }
                    if (empty($data['amount_right'])) {
                        $data['item_right'] = null;
                    }
                    $this->pdf->draw($body_map, $data, $forceY, $shiftY);
                    $forceY += $line_height;
                    $totalLeft  += $data['amount_left'];
                    $totalRight += $data['amount_right'];
                }
                $data = [
                    'amount_left'  => $totalLeft,
                    'amount_right' => $totalRight,
                ];
                $this->pdf->draw($footer_map, $data, $forceY, $shiftY);
                $shiftY += 99;
            }
        }

        if ($locked) {
            $file_name = [
                'P' => 'payment',
                'R' => 'receipt',
                'T' => 'transfer',
            ];
            $year = date('Y', strtotime($begin));
            $file = $this->getPdfPath($year, 'taxation', $file_name[$category] . '.pdf');
            $this->outputPdf(basename($file), dirname($file), true, $locked);
        } else {
            $this->pdf->output($template);
        }
    }
}
