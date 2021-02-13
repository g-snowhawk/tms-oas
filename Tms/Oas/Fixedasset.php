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
use Tms\Oas\Taxation;
use Tms\Pdf;

/**
 * Category management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Fixedasset extends Taxation
{
    /**
     * Using common accessor methods
     */
    use \Tms\Accessor;

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $paths = $this->view->getPaths();
        $this->pdf = new Pdf($paths);
    }

    protected function save()
    {
        if ($this->request->param('profile') === '1') {
            $this->request->post('id', $this->uid);
        } else {
            $id = $this->request->POST('id');
            $check_type = (empty($id)) ? 'create' : 'update';
            $is_parent = ($check_type === 'update') ? $this->isParent($id) : false;
            if (false === $is_parent) {
                $this->checkPermission('user.'.$check_type);
            }
        }

        $post = $this->request->post();

        $table = 'fixed_assets';
        $skip = ['id', 'userkey', 'modify_date'];

        $valid = [];
        $valid[] = ['vl_item', 'item', 'empty'];
        $valid[] = ['vl_title', 'title', 'empty'];
        $valid[] = ['vl_quantity', 'quantity', 'empty'];
        $valid[] = ['vl_acquire', 'acquire', 'empty'];
        $valid[] = ['vl_price', 'price', 'empty'];
        $valid[] = ['vl_location', 'location', 'empty'];
        $valid[] = ['vl_durability', 'durability', 'empty'];
        $valid[] = ['vl_depreciate_type', 'depreciate_type', 'empty'];
        $valid[] = ['vl_depreciate_rate', 'depreciate_rate', 'blank'];
        //$valid[] = ['vl_residual_value', 'residual_value', 'empty'];
        $valid[] = ['vl_official_ratio', 'official_ratio', 'empty'];

        if (!$this->validate($valid)) {
            return false;
        }

        $this->db->begin();

        $fields = $this->db->getFields($this->db->TABLE($table));
        $permissions = [];
        $save = [];
        $raw = [];
        foreach ($fields as $field) {
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                $save[$field] = $post[$field];
            }
        }

        if (empty($post['id'])) {
            $save['userkey'] = $this->uid;
            $result = $this->db->insert($table, $save, $raw);
        } else {
            $result = $this->db->update($table, $save, 'id = ?', [$post['id']], $raw);
        }
        if ($result !== false) {
            return $this->db->commit();
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }
}
