<?php

// =====================================================================
//
// This program is a standard component of Yet Another Web Programming
// (Yawp) foundation for rapid application development with PHP.  For
// more information, see <http://phpyawp.com/>.
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


/**
* Report all errors; conf will override this later.
*/
ini_set('error_reporting', E_ALL);


/**
* Yawp state is "in the process of starting up".
*/
define('YAWP_STATE_STARTING', 1);


/**
* Yawp state is "started and returned to calling script".
*/
define('YAWP_STATE_STARTED',  2);


/**
* Yawp state is "in the process of stopping".
*/
define('YAWP_STATE_STOPPING', 3);


/**
* Yawp state is "stopped and returned to calling script".
*/
define('YAWP_STATE_STOPPED',  4);


/**
* Error when Yawp::start() cannot find the starting config file.
*/
define('YAWP_ERR_STARTING_CONF', -1);


/**
* Error when Yawp::start() cannot find the redirect config file.
*/
define('YAWP_ERR_REDIRECT_CONF', -2);


/**
* The error messages when Yawp cannot find files.
*/
if (! isset($GLOBALS['_Yawp']['err'])) {
    $GLOBALS['_Yawp']['err'] = array(
        YAWP_ERR_STARTING_CONF => 'could not read starting config file',
        YAWP_ERR_REDIRECT_CONF => 'could not read redirect config file'
    );
}

/**
* The default path to the config file.
*/
if (! defined('YAWP_CONF_PATH')) {
    define('YAWP_CONF_PATH', $_SERVER['DOCUMENT_ROOT'] . '/Yawp.conf.php');
}


/**
*
* Yawp provides an encapsulated object suite foundation.
*
* To start a Yawp session, just include or require this file, and call
* Yawp::start(). Alternatively, call Yawp::start() with the path to a
* config file as the only parameter.
*
* Use Yawp methods themselves statically.
*   Yawp::start();
*   Yawp::getGet('get_var', 'default val');
*   Yawp::getPost('post_var', 'default val')
*   Yawp::stop();
*
* Access Yawp property objects with getObject().
*   $auth =& Yawp::getObject('Auth');
*   $auth->checkAuth();
*
*   $db =& Yawp::getObject('DB');
*   $db->query(...);
*
* $Id: Yawp.php,v 1.16 2006/03/07 04:39:13 justinrandell Exp $
*
* @author Paul M. Jones <pmjones@ciaweb.net>
*
* @version 1.2.0 stable
*
*/

class Yawp {


    /**
    *
    * A PEAR Auth instance.
    *
    * @access public
    *
    * @var object
    *
    */

    var $Auth;


    /**
    *
    * A PEAR Benchmark_Timer instance.
    *
    * @access public
    *
    * @var object
    *
    */

    var $Benchmark_Timer;


    /**
    *
    * A PEAR Cache_Lite instance.
    *
    * @access public
    *
    * @var object
    *
    */

    var $Cache_Lite;


    /**
    *
    * A PEAR DB instance.
    *
    * @access public
    *
    * @var object
    *
    */

    var $DB;


    /**
    *
    * A PEAR Log composite instance.
    *
    * @access public
    *
    * @var object
    *
    */

    var $Log;


    /**
    *
    * A PEAR Var_Dump instance.
    *
    * @access public
    *
    * @var object
    *
    */

    var $Var_Dump;


    /**
    *
    * Record of the last authentication error code constant.
    *
    * @access public
    *
    * @var int If an authentication error occurred, an AUTH_* constant
    * (AUTH_IDLED, AUTH_EXPIRED, or AUTH_WRONG_LOGIN), otherwise false.
    *
    */

    var $authErr = false;


    /**
    *
    * The array of configuration groups and values from the config file.
    *
    * @access public
    *
    * @var array
    *
    */

    var $conf = array();


    /**
    *
    * The list of optional Yawp property objects.
    *
    * There are classes that Yawp knows how to auto-load and auto-configure
    * as Yawp property objects.
    *
    * @access public
    *
    * @var array An associative array where the key is the config file group
    * name (also the property name) and the value is the file to include.
    *
    */

    var $objectConf = array(
        'Auth'            => 'Auth.php',
        'Benchmark_Timer' => 'Benchmark/Timer.php',
        'Cache_Lite'      => 'Cache/Lite.php',
        'DB'              => 'DB.php',
        'Log'             => 'Log.php',
        'Var_Dump'        => 'Var_Dump.php'
    );


    /**
    *
    * An array of "subordinate" PEAR Log objects.
    *
    * These are children to the composite log object.
    *
    * @access public
    *
    * @var bool
    *
    * @see Yawp::log()
    *
    */

    var $subLog = array();


    /**
    *
    * The "state" of Yawp.
    *
    * Indicates if Yawp is starting up, started and in the main,
    * stopping, or stopped.  If null/0/false, Yawp has not yet started.
    *
    * @access public
    *
    * @var int
    *
    */

    var $state = null;


    /**
    *
    * Return a static $Yawp object and its properties.
    *
    * @static
    *
    * @access public
    *
    * @return object A Yawp object.
    *
    */

    function &singleton()
    {
        static $Yawp;

        if (! isset($Yawp)) {
            // don't use =& here, because a static variable is not
            // allowed to be a reference.  This is a bug with PHP.
            $Yawp = new Yawp;
        }

        return $Yawp;
    }


    /**
    *
    * Initializes the Yawp singleton.
    *
    * Starts a session, starts the timer, loads objects, and processes
    * authentication.
    *
    * This is a monster method that does *everything* for the startup.
    * In theory I should break it down into sub-methods, but that would
    * expose methods that I don't want publically available; to make
    * them private would break the gentlemens' agreement on calling
    * private methods from outside the class, which is what would be
    * required using a singleton pattern.
    *
    * @param string $confFile An aribtrary config file location; default
    * is null, in which case the method looks for the config file
    * Yawp.conf.php at $_SERVER['DOCUMENT_ROOT'].
    *
    * @access public
    *
    * @return void
    *
    */

    function start($confFile = null)
    {
        // -------------------------------------------------------------
        //
        // Preliminaries.
        //

        $Yawp =& Yawp::singleton();

        // Has Yawp already been started?
        if ($Yawp->state) {
            // yes, don't re-start it
            return;
        }

        // -------------------------------------------------------------
        //
        // Load the config file file text; we need it now so we can
        // "look ahead" for object loading, redirects, etc.
        //

        // did the user specify a config file location?
        if (is_null($confFile)) {
            // no, so go to the default
            $confFile = YAWP_CONF_PATH;
        }

        // load the base conf file text
        $confText = Yawp::getFile($confFile);

        // could we load it?
        if ($confText === false) {

            $msg = 'Yawp::start() ' .
                $GLOBALS['_Yawp']['err'][YAWP_ERR_STARTING_CONF] .
                " '$confFile'";

            // HALT THE SCRIPT
            trigger_error(htmlspecialchars($msg), E_USER_ERROR);

        } else {
            $confText = trim($confText);
        }

        // does the first line start with a slash?
        if (substr($confText, 0, 1) == '/') {

            // the default config file redirects us to the "real" config
            // file. treat the first line as an absolute file path. we
            // add a \n to the confText so that we know we have at least
            // one newline at the end.
            $confText .= "\n";
            $confFile = substr(
                $confText, 0, strpos($confText, "\n")
            );

            // load up the new config text
            $confText = Yawp::getFile($confFile);

            // could we load it?
            if ($confText === false) {

                $msg = 'Yawp::start() ' .
                    $GLOBALS['_Yawp']['err'][YAWP_ERR_REDIRECT_CONF] .
                    " '$confFile'";

                // HALT THE SCRIPT
                trigger_error(htmlspecialchars($msg), E_USER_ERROR);

            } else {
                $confText = trim($confText);
            }
        }

        // force newlines at the fore and aft for parsing.
        $confText = "\n" . $confText . "\n";


        // -------------------------------------------------------------
        //
        // Yawp gets started!  Load library files here so that the
        // constants in those files are available when we parse the
        // config file.
        //

        $Yawp->state = YAWP_STATE_STARTING;

        // load optional library files.  we do a lookahead on the config
        // file to see if they have config groups noted; if so, load
        // the repsective classes.
        foreach ($Yawp->objectConf as $name => $file) {
            if (strpos($confText, "\n[$name]\n") !== false) {
                include_once $file;
            }
        }

        // as long as we're doing lookaheads, find out how many
        // log objects we're going to need; this will help later
        // when we instantiate log objects.
        $logCount = preg_match_all('/\n\[Log\]\n/', $confText, $tmp);
        unset($tmp);


        // -------------------------------------------------------------
        //
        // Look at $confText and define constants from the [CONSTANT]
        // config file group.
        //

        $regex = '/^((\[CONSTANT\])\n(.*)\n)(\[|\s|$)/Umsi';
        preg_match($regex, $confText, $matches);

        if (isset($matches[1])) {

            // we found a [CONSTANT] group; parse into an array.
            // becuase we leave out the [CONSTANT] line, all the
            // values come back as part of the [Root] group,
            // which is why we extract from that.
            $tmp = explode("\n", $matches[3]);
            $tmp2 = Yawp::parseConfLines($tmp);
            $const = $tmp2['Root'];
            unset($tmp);
            unset($tmp2);

            // define each constant
            foreach ($const as $key => $val) {

                // if we find more than one constant with the
                // same name, only honor the first one.
                if (is_array($val)) {
                    $val = array_shift($val);
                }

                // force slashes on DOCUMENT_ROOT and HREF_BASE endings
                if (($key == 'DOCUMENT_ROOT' || $key == 'HREF_BASE') &&
                    substr($val, -1) != '/') {
                    $val .= '/';
                }

                // define the constant
                if (! defined($key)) {
                    define($key, $val);
                }
            }

            // clean up
            unset($const);

            // remove the [CONSTANT] group from the source
            $confText = str_replace($matches[1], '', $confText);
        }

        // was the DOCUMENT_ROOT constant set in the config file?
        if (! defined('DOCUMENT_ROOT')) {
            // no, so default-define it here, add a trailing slash
            define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/');
        }

        // was HREF_BASE constant set in the config file?
        if (! defined('HREF_BASE')) {
            // no, so default-define it here
            define('HREF_BASE', '/');
        }

        // was HTTP_HOST constant set in the config file?
        if (! defined('HTTP_HOST')) {
            // no, so default-define it here
            define('HTTP_HOST', $_SERVER['HTTP_HOST']);
        }


        // -------------------------------------------------------------
        //
        // Expand %CONSTANT% tags in the config text, then parse the
        // source text into the $conf array.
        //
        // This is where we (finally) set up $Yawp->conf.
        //

        foreach (get_defined_constants() as $key => $val) {
            $confText = str_replace('%' . $key . '%', $val, $confText);
        }

        $lines = explode("\n", $confText);
        $Yawp->conf = Yawp::parseConfLines($lines);

        // clean up
        unset($lines);
        unset($confText);


        // -------------------------------------------------------------
        //
        // Reset the error level
        //

        ini_set(
            'error_reporting',
            Yawp::getConfElem('Yawp', 'error_reporting', E_ALL)
        );


        // -------------------------------------------------------------
        //
        // Configure and start the session, but only if session_start
        // is true, or Auth is configured.  Turn off Auth and
        // session_start to handle sessions yourself.
        //

        if (Yawp::getConfElem('Yawp', 'session_start', true) ||
            Yawp::getConfGroup('Auth', false)) {

            //
            // Set zero expiry time and cache_limiter to
            // 'private'.  This should prevent caches from
            // caching the cookie data, and also ensure that
            // the cookie lasts as long as the browser.
            //
            session_name(
                Yawp::getConfElem('Yawp', 'session_name', 'YAWP'));
            session_cache_expire(0);
            session_cache_limiter('private');
            ini_set('session.gc_maxlifetime', 86400);

            // set session parameters
            //
            // You cannot set a cookie domain parameter to
            // be a TLD -- or any simple string like "localhost".
            // It isn't allowed by the HTTP protocol, for security
            // reasons.  Omitting the domain parameter completely
            // seems to solve the problem for the case where the
            // server name is "localhost" or similar.
            //
            // Ref: http://www.php.net/manual/en/function.session-set-cookie-params.php
            //
            $http_host = Yawp::getConfElem('Yawp', 'session_domain', HTTP_HOST);
            if (strpos($http_host, '.')) {
                session_set_cookie_params(
                    Yawp::getConfElem('Yawp', 'session_lifetime', 0),
                    Yawp::getConfElem('Yawp', 'session_path'    , HREF_BASE),
                    $http_host,
                    Yawp::getConfElem('Yawp', 'session_secure'  , false)
                );
            } else {
                session_set_cookie_params(
                    Yawp::getConfElem('Yawp', 'session_lifetime', 0),
                    Yawp::getConfElem('Yawp', 'session_path'    , HREF_BASE)
                );
            }

            // silence starting calls per note from Lukas Smith;
            // apparently session_start() generates weird errors
            // in earlier versions of PHP 4
            @session_start();
        }


        // -------------------------------------------------------------
        //
        // Process user-defined prep scripts.  These are just like the
        // start scripts, except they get run before any of the internal
        // objects get created, rather than after.
        //

        if (isset($Yawp->conf['Yawp']['prep'])) {
            settype($Yawp->conf['Yawp']['prep'], 'array');
            foreach ($Yawp->conf['Yawp']['prep'] as $file) {
                if (trim($file) != '') {
                    Yawp::run($file);
                }
            }
        }


        // -------------------------------------------------------------
        //
        // Optionally instantiate Benchmark_Timer and start timer.  There
        // are no config options for Benchmark_Timer.  We start it here
        // because we want the timer to capture as much timing data as
        // possible.
        //

        // only create if not already created by a prep hook,
        // and then only if we have a Benchmark_Timer conf group.
        if (! is_object($Yawp->Benchmark_Timer) &&
            isset($Yawp->conf['Benchmark_Timer'])) {
            // create and start the timer
            $Yawp->Benchmark_Timer =& new Benchmark_Timer();
            $Yawp->Benchmark_Timer->start();
        } else {
            if (! is_object($Yawp->Benchmark_Timer))
                $Yawp->Benchmark_Timer = null;
        }


        // -------------------------------------------------------------
        //
        // Create an optional instance of Var_Dump.
        //

        // only create if not already created by a prep hook,
        // and then only if there is a Var_Dump conf group.
        $opts = Yawp::getConfGroup('Var_Dump', false);
        if (! is_object($Yawp->Var_Dump) && is_array($opts)) {

            $mode = Yawp::getConfElem(
                'Var_Dump', 'display_mode', 'HTML4_Table'
            );

            // if there are no elements in the [Var_Dump] group,
            // then display_mode element is not set.  set it!
            if (! isset($opts['display_mode'])) {
                $opts['display_mode'] = $mode;
            }

            // get rendering options, if any
            $rend = Yawp::getConfGroup("Var_Dump_$mode", array());

            // build the object
            $Yawp->Var_Dump =& Var_Dump::factory($opts, $rend);

            // clean up
            unset($mode);
            unset($rend);

        } else {
            if (! is_object($Yawp->Var_Dump))
                $Yawp->Var_Dump = null;
        }

        // clean up
        unset($opts);


        // -------------------------------------------------------------
        //
        // Create an optional instance of Cache_Lite
        //

        // only create if not already created by a prep hook,
        // and then only if there is a Cache_Lite conf group.
        $opts = Yawp::getConfGroup('Cache_Lite', false);
        if (! is_object($Yawp->Cache_Lite) && $opts) {
            $Yawp->Cache_Lite =& new Cache_Lite($opts);
        } else {
            if (! is_object($Yawp->Cache_Lite))
                $Yawp->Cache_Lite = null;
        }

        // clean up
        unset($opts);


        // -------------------------------------------------------------
        //
        // Create an optional instance of DB
        //

        // only create if not already created by a prep hook,
        // and then only if we have a DB DSN.
        $dsn = Yawp::getConfGroup('DB', false);
        if (! is_object($Yawp->DB) && $dsn) {
            $opts = Yawp::getConfGroup('DB_Options');
            $Yawp->DB =& DB::connect($dsn, $opts);
        } else {
            if (! is_object($Yawp->DB))
                $Yawp->DB = null;
        }

        // clean up
        unset($dsn);
        unset($opts);


        // -------------------------------------------------------------
        //
        // Create optional instance of Log and the various log objects.
        //

        // only create if not already created by a prep hook,
        // and then only if logs were requested in the conf file.
        if (! is_object($Yawp->Log) && $logCount > 0) {

            $Yawp->Log =& Log::singleton('composite');
            if ($logCount == 1) {
                // if there is only one Log entry, make it the 0-entry in
                // a greater array; that way the foreach loop will work
                // as expected.
                $tmp = array(Yawp::getConfGroup('Log'));
            } else {
                // get the multiple Log entries
                $tmp = Yawp::getConfGroup('Log');
            }

            foreach ($tmp as $key => $val) {

                // get the all-purpose keys needed for every log handler
                $handler = (isset($val['handler'])) ? $val['handler'] : false;
                $name = (isset($val['name'])) ? $val['name'] : '';
                $ident = (isset($val['ident'])) ? $val['ident'] : '';
                $level = (isset($val['level'])) ? $val['level'] : PEAR_LOG_DEBUG;

                // remove the all-purpose keys, this leaves us
                // with only the keys for the handler config
                unset($val['handler']);
                unset($val['name']);
                unset($val['ident']);
                unset($val['level']);

                // if no handler is specified, skip it.
                if (! $handler) {
                    continue;
                }

                // special case: if the handler is SQL, and no
                // dsn is set, and we have a DB object, use the
                // DB object.
                if ($handler == 'sql' && is_object($Yawp->DB) &&
                    (! isset($val['dsn']) || ! $val['dsn'])) {
                    $val['db'] = $Yawp->DB;
                }

                // create the sub-log ...
                $Yawp->subLog[$key] =& Log::singleton(
                    $handler, $name, $ident, $val, $level
                );

                // ... then add it to the composite
                $Yawp->Log->addChild($Yawp->subLog[$key]);

            }

            // clean up
            unset($key);
            unset($val);
            unset($handler);
            unset($name);
            unset($ident);
            unset($level);
            unset($tmp);

        } else {
            // logging not enabled
            if (! is_object($Yawp->Log))
                $Yawp->Log = null;
        }

        // clean up
        unset($logCount);


        // -------------------------------------------------------------
        //
        // Create optional instance of Auth.
        //

        // only create if not already created by a prep hook,
        // and then only if there is an [Auth] conf group.
        if (! is_object($Yawp->Auth) &&
            Yawp::getConfGroup('Auth', false)) {

            // authentication is on.
            // get the container type.
            $container = Yawp::getConfElem('Auth', 'container');

            // get the container parameters
            if ($container == 'File' || $container == 'SMBPasswd') {
                // these containers have only one parameter
                // (the file name)
                $params = Yawp::getConfElem("Auth_$container", 'file');
            } else {
                $params = Yawp::getConfGroup("Auth_$container");
            }

            // special case for Auth_DB and Auth_DBLite: if the 'dsn'
            // element is null/false/blank/unset, replace it with a
            // reference to $Yawp->DB
            if (($container == 'DB' || $container == 'DBLite') &&
                (! isset($params['dsn']) || ! $params['dsn']) &&
                is_object($Yawp->DB)) {

                $params['dsn'] = $Yawp->DB;
            }

            // instantiate the object
            $Yawp->Auth =& new Auth($container, $params, '', false);

        } else {
            // authentication not enabled
            if (! is_object($Yawp->Auth))
                $Yawp->Auth = null;
        }


        // -------------------------------------------------------------
        //
        // Process Auth login/logout/idle/expire/error events, but only
        // if we have an Auth object available.
        //

        if (is_object($Yawp->Auth)) {

            // hack to allow logins to work under both Auth 1.2.3 and
            // 1.3.0r2 ... does this really work?
            if (method_exists($Yawp->Auth, 'setAllowLogin')) {
                $Yawp->Auth->setAllowLogin(true);
            }

            // set idle time
            $Yawp->Auth->setIdle(
                Yawp::getConfElem('Auth', 'idle', 1800)
            );

            // set expire time
            $Yawp->Auth->setExpire(
                Yawp::getConfElem('Auth', 'expire', 3600)
            );

            // Process explicit login attempts.
            if (isset($_POST['LOGIN'])) {

                // start an authenticated session; this will
                // automatically check credentials ($_POST['username']
                // && $_POST['password']) if they were passed.
                $Yawp->Auth->start();

                // was the login successful?
                if ($Yawp->Auth->checkAuth()) {

                    // yes. clear any prior errors.
                    $Yawp->clearAuthErr();

                    //perform login hooks if they exist.
                    $tmp = Yawp::getConfElem('Yawp', 'login');
                    settype($tmp, 'array');

                    // include each file
                    foreach ($tmp as $file) {
                        if (trim($file) != '') {
                            Yawp::run($file);
                        }
                    }

                } else {

                    // login failed.  retain the error status in a
                    // session variable so we can keep it between
                    // redirects.

                    $Yawp->authErr = $Yawp->Auth->getStatus();
                    $_SESSION['_Yawp']['authErr'] = $Yawp->authErr;

                }

                // redirect if required.
                if (isset($_POST['location']) &&
                    trim($_POST['location']) != '') {
                    // THIS WILL END THE STARTUP SEQUENCE AND REDIRECT
                    // TO ANOTHER PAGE!
                    session_write_close();
                    header("Location: {$_POST['location']}");
                }

            }

            // Process explicit logout attempts.
            if (isset($_POST['LOGOUT'])) {

                // process any customized logout hook scripts
                $tmp = Yawp::getConfElem('Yawp', 'logout');
                settype($tmp, 'array');
                foreach ($tmp as $file) {
                    if (trim($file) != '') {
                        Yawp::run($file);
                    }
                }

                // actually log out
                $Yawp->Auth->logout();

                // redirect if required.
                if (isset($_POST['location']) &&
                    trim($_POST['location']) != '') {
                    // THIS WILL END THE STARTUP SEQUENCE AND REDIRECT
                    // TO ANOTHER PAGE!
                    session_write_close();
                    header("Location: {$_POST['location']}");
                }
            }


            // Process authentication errors and idle/expire timeouts.
            // get the Auth_Err status from a prior login if
            // it exists, or check the current authentication
            // state if not.
            if (isset($_SESSION['_Yawp']['authErr'])) {

                // prior auth error was set, capture it for this
                // page load.
                $Yawp->authErr = $_SESSION['_Yawp']['authErr'];

            } else {

                // check current status
                $Yawp->Auth->checkAuth();
                $Yawp->authErr = $Yawp->Auth->getStatus();

            }

            // were there auth errors?
            if ($Yawp->authErr) {

                // run the autherr hooks
                $tmp = Yawp::getConfElem('Yawp', 'authErr');
                settype($tmp, 'array');
                foreach ($tmp as $file) {
                    if (trim($file) != '') {
                        Yawp::run($file);
                    }
                }

            }
        }


        // -------------------------------------------------------------
        //
        // Process user-defined start scripts
        //

        $tmp = Yawp::getConfElem('Yawp', 'start');
        settype($tmp, 'array');
        foreach ($tmp as $file) {
            if (trim($file) != '') {
                Yawp::run($file);
            }
        }

        // done!
        $Yawp->state = YAWP_STATE_STARTED;
    }


    /**
    *
    * Run the user-defined "stop" scripts, then stop the timer and set the
    * state of Yawp.
    *
    * @static
    *
    * @access public
    *
    * @return void
    *
    */

    function stop()
    {
        $Yawp =& Yawp::singleton();

        // update the Yawp state
        $Yawp->state = YAWP_STATE_STOPPING;

        // run the user-defined shutdown scripts
        $tmp = Yawp::getConfElem('Yawp', 'stop');
        settype($tmp, 'array');
        foreach ($tmp as $file) {
            if (trim($file) != '') {
                Yawp::run($file);
            }
        }

        // close the log, stop the timer, set the Yawp state
        Yawp::logClose();
        Yawp::timerStop();
        $Yawp->state = YAWP_STATE_STOPPED;
    }


    /**
    *
    * Runs a hook script in an isolated environment.
    *
    * @static
    *
    * @access public
    *
    * @param string The path of the file to include.
    *
    * @return void
    *
    */

    function run()
    {
        include func_get_arg(0);
    }


    // =================================================================
    //
    // Yawp property accessor methods
    //
    // =================================================================


    /**
    *
    * Get the current Yawp state.
    *
    * @static
    *
    * @access public
    *
    * @return int A Yawp state constant.
    *
    */

    function getState()
    {
        $Yawp =& Yawp::singleton();
        return $Yawp->state;
    }


    /**
    *
    * Get a Yawp property object reference (Auth, DB, Cache_Lite, etc).
    *
    * @static
    *
    * @param string $objname The object property to retrieve.
    *
    * @return object|bool The property object, or void if the property
    * does not exist or is not an object.
    *
    */

    function &getObject($objname)
    {
        $Yawp =& Yawp::singleton();
        if (isset($Yawp->$objname) && is_object($Yawp->$objname)) {
            return $Yawp->$objname;
        } else {
            // have to return **something** to assuage PHP 4.4.x and
            // 5.0.x (avoids "only variables can be returned by
            // reference" error)
            $obj = null;
            return $obj;
        }
    }


    /**
    *
    * Get the last authentication error code, if any.
    *
    * Gets the authentication error code (idle, expire, wrong login),
    * even after a redirect, but retains the error state.
    *
    * @static
    *
    * @access public
    *
    * @return mixed Boolean false if the there are no recorded
    * authentication errors, or an integer error code from Auth.
    *
    */

    function getAuthErr()
    {
        $Yawp =& Yawp::singleton();
        return $Yawp->authErr;
    }


    /**
    *
    * Get the last authentication error message, if any.
    *
    * Retains the current error state.
    *
    * @static
    *
    * @access public
    *
    * @return mixed Null if the there are no recorded authentication
    * errors, or the error message from the config file.
    *
    */

    function getAuthErrMsg()
    {
        $Yawp =& Yawp::singleton();
        $err = $Yawp->authErr;
        if ($err) {
            return Yawp::getConfElem('Auth', (string) $err);
        } else {
            return null;
        }
    }


    /**
    *
    * Get the last authentication error code, if any, then clear it.
    *
    * Gets any authentication error code (idle, expire, wrong login),
    * even after a redirect, then clears the error state.
    *
    * @static
    *
    * @access public
    *
    * @return mixed Boolean false if the there are no recorded
    * authentication errors, or an integer error code from Auth.
    *
    */

    function clearAuthErr()
    {
        $Yawp =& Yawp::singleton();
        $err = $Yawp->authErr;
        $Yawp->authErr = false;
        if (isset($_SESSION['_Yawp']['authErr'])) {
            unset($_SESSION['_Yawp']['authErr']);
        }
        return $err;
    }


    /**
    *
    * Get the last authentication error message, if any, then clear it.
    *
    * @static
    *
    * @access public
    *
    * @return mixed Null if the there are no recorded authentication
    * errors, or the error message from the config file.
    *
    */

    function clearAuthErrMsg()
    {
        $Yawp =& Yawp::singleton();
        $err = $Yawp->authErr;
        $Yawp->authErr = false;
        if (isset($_SESSION['_Yawp']['authErr'])) {
            unset($_SESSION['_Yawp']['authErr']);
        }

        if ($err) {
            return Yawp::getConfElem('Auth', (string) $err);
        } else {
            return null;
        }
    }


    // =================================================================
    //
    // Config file methods
    //
    // =================================================================


    /**
    *
    * Get file contents, and optionally convert line endings.
    *
    * @static
    *
    * @param string $path The path to the file.
    *
    * @param bool $incl Use the include_path? Default false.
    *
    * @param bool $conv Convert DOS and Mac line endings to Unix
    * newlines?  Default true.
    *
    * @return bool|string Boolean false if file not found, or a string
    * of the file contents.
    *
    */

    function getFile($path, $incl = false, $conv = true)
    {
        // does the file exist, and is it readable?
        if (! file_exists($path) || ! is_readable($path)) {
            return false;
        }

        // load the file
        if (function_exists('file_get_contents')) {

            // native PHP
            $data = file_get_contents($path, $incl);

        } else {

            // file_get_contents emulation
            $data = false;
            $fp = @fopen($path, 'rb', $incl);

            if ($fp) {
                $size = @filesize($path);
                if ($size) {
                    $data = fread($fp, $size);
                } else {
                    while (! feof($fp)) {
                        $data .= fread($fp, 8192);
                    }
                }
                fclose($fp);
            }
        }

        // convert line endings?
        if ($conv) {
            // replace \r\n with \n (DOS -> Unix)
            $data = str_replace("\r\n", "\n", $data);

            // replace \r with \n (Mac -> Unix)
            $data = str_replace("\r", "\n", $data);
        }

        // done!
        return $data;
    }


    /**
    *
    * Convert individual lines from a Yawp config file to an array.
    *
    * @static
    *
    * @access public
    *
    * @param array $lines The array of lines from the Yawp.conf.php
    * file.
    *
    * @return array The parsed array of Yawp.conf.php values.
    *
    */

    function parseConfLines($lines)
    {
        $data = array();
        $group = 'Root';
        $groupNum = array('Root' => '0');

        foreach ($lines as $line) {

            $line = trim($line);

            // is the line blank or commented?
            if ($line == '' || $line{0} == ';' || $line{0} == '#') {
                continue;
            }

            // is the line a group ID?
            if ($line{0} == '[' && substr($line, -1) == ']') {

                $group = trim(substr($line, 1, -1));

                if (isset($groupNum[$group])) {
                    $groupNum[$group] ++;
                } else {
                    $groupNum[$group] = 0;
                }

                // if this is the first go-around for the group,
                // place an array for it.  this allows up to have
                // just group names without any elements.
                if (! isset($data[$group])) {
                    $data[$group] = array();
                }

                continue;
            }

            // does the line have an "=" sign?
            $pos = strpos($line, '=');
            if ($pos === false) {
                // no "=" so treat the line as a key with a
                // null value
                $key = $line;
                $val = null;
            } elseif ($pos == 0) {
                // "=" is at the start, so no key specified,
                // make it an integer count with the line as
                // the value
                $key = count($data[$group][$groupNum[$group]]);
                $val = $line;
            } else {
                // normal case
                $key = trim(substr($line, 0, $pos));
                $val = trim(substr($line, $pos+1));
            }

            // convert booleans and nulls
            switch (strtolower($val)) {
            case 'true':
                $val = true;
                break;
            case 'false':
                $val = false;
                break;
            case 'null':
                $val = null;
                break;
            }

            // retain the key and value
            $data[$group][$groupNum[$group]][$key][] = $val;
        }

        // for element keys that only iterate once,
        // back them off from arrays to scalars
        foreach ($data as $groupName => $groupSet) {
            foreach ($groupSet as $groupKey => $groupVal) {
                foreach ($groupVal as $elemName => $elemSet) {
                    if (count($elemSet) == 1) {
                        $data[$groupName][$groupKey][$elemName] = $elemSet[0];
                    }
                }
            }
        }

        // for group names that only iterate once,
        // back them off from arrays to scalars
        foreach ($data as $groupName => $groupSet) {
            if (count($groupSet) == 1 && is_array($groupSet)) {
                $data[$groupName] = $groupSet[0];
            }
        }

        // done!
        return $data;
    }



    /**
    *
    * Convenience method to get a configuration group array.
    *
    * Returns a blank default value if the group is not set.
    *
    * @static
    *
    * @access public
    *
    * @param string $group The name of the group to retrieve.
    *
    * @param mixed $blank If the group is not set, return this value
    * instead.
    *
    * @return mixed The value of the configuration group.
    *
    */

    function getConfGroup($group, $blank = array())
    {
        $Yawp =& Yawp::singleton();

        if (isset($Yawp->conf[$group])) {
            return $Yawp->conf[$group];
        } else {
            return $blank;
        }
    }


    /**
    *
    * Convenience method to get a configuration group-element.
    *
    * Returns a blank default value if the element is not set.
    *
    * @static
    *
    * @access public
    *
    * @param string $group The name of the group.
    *
    * @param string $elem The name of the element in the group.
    *
    * @param mixed $blank If the group-element is not set, return this
    * value instead.
    *
    * @return mixed The value of the configuration group-element.
    *
    */

    function getConfElem($group, $elem, $blank = null)
    {
        $Yawp =& Yawp::singleton();

        if (isset($Yawp->conf[$group][$elem])) {
            return $Yawp->conf[$group][$elem];
        } else {
            return $blank;
        }
    }


    // =================================================================
    //
    // PATH_INFO, GET, and POST variable accessor methods
    //
    // =================================================================


    /**
    *
    * Get the raw value of any superglobal element.
    *
    * Use this to get the raw value of any superglobal ($_GET, $_POST,
    * $_SERVER, $_ENV, etc).  Automatically checks if the element is
    * set; if not, returns the $default value. DOES NOT make the value
    * safe by stripping slashes, tags, etc.
    *
    * @static
    *
    * @access public
    *
    * @param string $name The name of the superglobal to work with
    * ('get', 'post', etc).
    *
    * @param string $key The element in the superglobal array; if null,
    * returns the whole array.
    *
    * @param mixed $default If the requested array element is
    * not set, return this value.
    *
    * @return mixed The array element value (if set), or the
    * $default value (if not).
    *
    */

    function getSG($name, $key = null, $default = null)
    {
        $name = strtoupper('_' . $name);
        if (is_null($key) && isset($GLOBALS[$name])) {
            return $GLOBALS[$name];
        } elseif (isset($GLOBALS[$name][$key])) {
            return $GLOBALS[$name][$key];
        } else {
            return $default;
        }
    }


    /**
    *
    * Safely gets a path info element from the URL.
    *
    * The path info is the part of the URL after the script name.
    * For example, in this URL ...
    *
    *     http://example.com/index.php/this/that/other
    *
    * ... the path info would be '/this/that/other', which is converted
    * into a sequential array(0 => 'this', 1 => 'that', 2 => 'other').
    *
    * Automatically checks if the position is set; if not, returns a
    * default value.  Strips slashes and HTML tags automatically.
    *
    * @static
    *
    * @access public
    *
    * @param string $pos The array position; if null, returns the whole
    * array.
    *
    * @param mixed $default If the requested array position is not set,
    * return this value.
    *
    * @return mixed The array position value (if set), or the $default
    * value (if not).
    *
    */

    function getPathInfo($pos = null, $default = null)
    {
        // is there any path info to begin with?
        if (! isset($_SERVER['PATH_INFO']) || empty($_SERVER['PATH_INFO'])) {
            return $default;
        }

        // create an array, and drop the first element (after
        // explode(), the first element will always be empty)
        $info = explode('/', $_SERVER['PATH_INFO']);
        array_shift($info);

        // select, dispel, and return
        if (is_null($pos)) {
            // no key selected, return the whole $info array
            return Yawp::dispelMagicQuotes($info, true);
        } elseif (isset($info[$pos])) {
            // looking for a specific position
            return Yawp::dispelMagicQuotes($info[$pos], true);
        } else {
            // specified position does not exist
            return $default;
        }
    }


    /**
    *
    * Safely get the value of an element from the $_GET array.
    *
    * Automatically checks if the element is set; if not, returns a
    * default value.  Strips slashes and HTML tags automatically.
    *
    * @static
    *
    * @access public
    *
    * @param string $key The array element; if null, returns the whole
    * array.
    *
    * @param mixed $default If the requested array element is
    * not set, return this value.
    *
    * @return mixed The array element value (if set), or the
    * $default value (if not).
    *
    */

    function getGet($key = null, $default = null)
    {
        if (is_null($key) && isset($_GET)) {
            // no key selected, return the whole $_GET array
            return Yawp::dispelMagicQuotes($_GET, true);
        } elseif (isset($_GET[$key])) {
            // looking for a specific key
            return Yawp::dispelMagicQuotes($_GET[$key], true);
        } else {
            // specified key does not exist
            return $default;
        }
    }


    /**
    *
    * Safely get the value of an element from the $_POST array.
    *
    * Automatically checks if the element is set; if not, returns a
    * default value.  Strips slashes (but not HTML tags) automatically.
    *
    * @static
    *
    * @access public
    *
    * @param string $key The array element; if null, returns the whole
    * array.
    *
    * @param mixed $default If the requested array element is
    * not set, return this value.
    *
    * @return mixed The array element value (if set), or the
    * $default value (if not).
    *
    */

    function getPost($key = null, $default = null)
    {
        if (is_null($key) && isset($_POST)) {
            // no key selected, return the whole $_POST array
            return Yawp::dispelMagicQuotes($_POST);
        } elseif (isset($_POST[$key])) {
            // looking for a specific key
            return Yawp::dispelMagicQuotes($_POST[$key]);
        } else {
            // specified key does not exist
            return $default;
        }
    }


    /**
    *
    * Merges a base array of values with a new-data array.
    *
    * This method is similar to array_merge(); if a key from the base
    * set is not in the new-data set, it remains at the base value.
    * However, new keys in the new-data set are never added to the
    * base set.
    *
    * @access public
    *
    * @param array $base The base key-value pairs.
    *
    * @param array $data The new data to be merged with the $base array.
    *
    * If a key exists in $data but does not exist in $base, it is
    * **not** added to $base; otherwise, the $data value for a key
    * overrides the $base value for that key.
    *
    * @return array The $base array, with overrides from $data.
    *
    */

    function merge($base, $data = null)
    {
        settype($base, 'array');
        settype($data, 'array');

        foreach ($base as $key => $val) {
            if (isset($data[$key])) {
                $base[$key] = $data[$key];
            }
        }

        return $base;
    }


    /**
    *
    * Recursively strip slashes on a value if magic_quotes_gpc is on.
    *
    * Also (optionally) applies strip_tags() to the value regardless of
    * magic_quotes_gpc.
    *
    * Credits to Chuck Hagenbuch and Jon Parise on this method; I would
    * not have thought to implement it without looking at the Horde
    * library.  This method is functionally identical to theirs.
    *
    * @static
    *
    * @access public
    *
    * @param string $var The variable to strip slashes from.
    *
    * @param bool $stripTags If true, also strips HTML tags from the
    * variable.
    *
    * @return mixed The variable value without slashes.
    *
    */

    function dispelMagicQuotes($var, $stripTags = false)
    {
        static $magicQuotes;

        if (! isset($magicQuotes)) {
            $magicQuotes = get_magic_quotes_gpc();
        }

        if ($magicQuotes || $stripTags) {

            if (is_array($var)) {

                foreach ($var as $k => $v) {
                    $var[$k] = Yawp::dispelMagicQuotes($v, $stripTags);
                }

            } else {

                if ($magicQuotes) {
                    $var = stripslashes($var);
                }

                if ($stripTags) {
                    $var = strip_tags($var);
                }
            }
        }

        return $var;
    }


    // =================================================================
    //
    // Auth convenience methods
    //
    // =================================================================


    /**
    *
    * Gets the current authenticated username.
    *
    * Returns boolean false if authentication is enabled but the user is
    * not signed in, or returns null if authentication is not enabled.
    *
    * @static
    *
    * @access public
    *
    * @return mixed Null if authentication is not turned on, boolean
    * false if authentication is on but the user is not signed in, or
    * the username (when authentication is on and the user has signed
    * in).
    *
    */

    function authUsername()
    {
        $auth =& Yawp::getObject('Auth');
        if (! $auth) {
            return null;
        } elseif ($auth->getAuth()) {
            return $auth->getUsername();
        } else {
            return false;
        }
    }


    // =================================================================
    //
    // Benchmark_Timer convenience methods
    //
    // =================================================================


    /**
    *
    * Mark the time with a message.
    *
    * @static
    *
    * @access public
    *
    * @param string $msg The message to log.
    *
    * @return void
    *
    */

    function timerMark($msg)
    {
        if ($timer =& Yawp::getObject('Benchmark_Timer')) {
            $timer->setMarker($msg);
        }
    }


    /**
    *
    * Display all the timer marks.
    *
    * @static
    *
    * @access public
    *
    * @return void
    *
    */

    function timerDisplay()
    {
        if ($timer =& Yawp::getObject('Benchmark_Timer')) {
            $timer->display();
        }
    }


    /**
    *
    * Stop the timer.
    *
    * @static
    *
    * @access public
    *
    * @return void
    *
    */

    function timerStop()
    {
        if ($timer =& Yawp::getObject('Benchmark_Timer')) {
            $timer->stop();
        }
    }


    // =================================================================
    //
    // Cache_Lite convenience methods
    //
    // =================================================================


    /**
    *
    * Test if a cache is available and (if yes) return it.
    *
    * @access public
    *
    * @param string $id cache id
    *
    * @param string $group name of the cache group
    *
    * @param boolean $noTest if set to true, the cache validity won't be
    * tested
    *
    * @return mixed String data if cached data available, boolean false
    * if cached data not available, or null if caching not turned on.
    *
    */

    function cacheGet($id, $group = 'default', $noTest = false)
    {
        if ($cache =& Yawp::getObject('Cache_Lite')) {
            return $cache->get($id, $group, $noTest);
        } else {
            return null;
        }
    }


    /**
    *
    * Save some data in a cache file.
    *
    * @access public
    *
    * @param string $data data to put in cache (can be another type than
    * strings if automaticSerialization is on)
    *
    * @param string $id cache id
    *
    * @param string $group name of the cache group
    *
    * @return boolean True if no problem, false if a problem, null if
    * caching not turned on.
    *
    */

    function cacheSave($data, $id, $group = 'default')
    {
        if ($cache =& Yawp::getObject('Cache_Lite')) {
            return $cache->save($data, $id, $group);
        } else {
            return null;
        }
    }


    /**
    *
    * Remove a cache file.
    *
    * @access public
    *
    * @param string $id cache id
    *
    * @param string $group name of the cache group
    *
    * @return boolean True if no problem, false if a problem,
    * or null if caching not turned on.
    *
    */

    function cacheRemove($id, $group = 'default')
    {
        if ($cache =& Yawp::getObject('Cache_Lite')) {
            return $cache->remove($id, $group);
        } else {
            return null;
        }
    }


    /**
    *
    * Clean the cache of all data.
    *
    * If no group is specified all cache files will be destroyed, else
    * only cache files of the specified group will be destroyed.
    *
    * @access public
    *
    * @param string $group name of the cache group
    *
    * @return boolean True if no problem, false if a problem,
    * or null if caching not turned on.
    *
    */

    function cacheClean($group = false)
    {
        if ($cache =& Yawp::getObject('Cache_Lite')) {
            return $cache->clean($group);
        } else {
            return null;
        }
    }


    // =================================================================
    //
    // Log convenience methods
    //
    // =================================================================


    /**
    *
    * Send a message to the log.
    *
    * @static
    *
    * @param string $message The message to log.
    *
    * @param int $level The PEAR_LOG_* level to log as.
    *
    * @return void
    *
    */

    function log($message, $level = PEAR_LOG_INFO)
    {
        if ($log =& Yawp::getObject('Log')) {
            $log->log($message, $level);
        }
    }


    /**
    *
    * Close the log.
    *
    * @static
    *
    * @param string $message The message to log.
    *
    * @param int $level The PEAR_LOG_* level to log as.
    *
    * @return void
    *
    */

    function logClose()
    {
        if ($log =& Yawp::getObject('Log')) {
            $log->close();
        }
    }


    // =================================================================
    //
    // Var_Dump convenience methods
    //
    // =================================================================


    /**
    *
    * Dump a variable to the screen with with optional label.
    *
    * @static
    *
    * @access public
    *
    * @param mixed $var The variable to display.
    *
    * @param string $label The label to display for the dump, if any.
    *
    * @return void
    *
    */

    function dump($var, $label = null)
    {
        if ($dump =& Yawp::getObject('Var_Dump')) {

            if (! is_null($label)) {
                print $label;
            }

            print $dump->toString($var);
        }
    }

    function generateCaptcha()
    {
        $int1 = rand(1, 5).rand(0, 5);
        $int2 = rand(1, 4).rand(0, 4);
        $_SESSION['captchapasswd'] = (string)($int1 + $int2);
        return "$int1 + $int2 =";
    }

    function checkCaptcha($captcha)
    {
        if (!array_key_exists('captchapasswd', $_SESSION) || $_SESSION['captchapasswd'] !== $captcha) {
            return false;
        }
        return true;
    }
}

?>
