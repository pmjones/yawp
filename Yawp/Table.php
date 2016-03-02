<?php

// =====================================================================
//
// This program is a component of the standard YAWP Framework (Yet
// Another Web Programming foundation) for rapid application development
// with PHP.  For more information, see <http://ciaweb.net/yawp/>.
//
// Copyright (C) 2004 Paul M. Jones. <pmjones@ciaweb.net>
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as
// published by the Free Software Foundation; either version 2.1 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// Lesser General Public License for more details.
//
// http://www.gnu.org/copyleft/lesser.html
//
// =====================================================================

// $Id: Table.php,v 1.2 2006/02/19 00:26:03 delatbabel Exp $

require_once 'DB/Table.php';

class Yawp_Table extends DB_Table {

    /**
    *
    * Configuration options as defined by the Yawp.conf.php file.
    *
    * @access public
    *
    * @var array
    *
    */

    var $conf = array();


    /**
    *
    * Constructor.
    *
    * @access public
    *
    */

    function Yawp_Table()
    {
        $class = get_class($this);
        $table  =  Yawp::getConfElem($class, 'table', $class);
        $create =  Yawp::getConfElem($class, 'create', 'safe');

        // prepare the $conf property values.  build them from whatever
        // $conf values are set by default, then override them with
        // anything from the Yawp.conf.php file
        $this->conf = array_merge($this->conf, Yawp::getConfGroup($class));

        // set local fetchmode and object
        $this->fetchmode = Yawp::getConfElem(
            $class, 'fetchmode', DB_FETCHMODE_ASSOC
        );

        $this->fetchmode_object_class = Yawp::getConfElem(
            $class, 'fetchmode_object_class', 'stdClass'
        );

        // unset local $conf values that aren't supposed to be there
        // any more ;-)
        unset($this->conf['fetchmode']);
        unset($this->conf['fetchmode_object_class']);
        unset($this->conf['table']);
        unset($this->conf['create']);

        // parent initialization; this is the heart of the constructor
        $yawp =& Yawp::singleton();
        parent::DB_Table($yawp->DB, $table, $create);
    }
}
?>