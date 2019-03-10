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

use Tms\Oas\Transfer;
use Exception;

/**
 * User management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Relational extends Transfer
{
    protected $saved_issue_date;
    protected $saved_page_number;

    private $caller;

    /**
     * Object constructor
     *
     * - Validate the caller has Tms\PackageInterface
     *   If caller has no interface then throw Exception
     */
    public function __construct()
    {
        $params = func_get_args();
        $this->caller = array_shift($params);

        if (false === is_subclass_of($this->caller, 'Tms\\PackageInterface')) {
            throw new Exception('No match application type');
        }

        call_user_func_array('parent::__construct', $params);
    }

    /**
     * Object destructor
     *
     * - Rewind current application to the caller
     */
    public function __destruct()
    {
        $this->caller->setCurrentApplication();
    }

    /**
     * Save the data receive interface.
     */
    public function save(): bool
    {
        return parent::save(true);
    }
}
