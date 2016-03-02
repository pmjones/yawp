;<?php die() ?>
; $Id: Yawp.conf-dist.php,v 1.1 2004/10/15 14:13:09 pmjones Exp $

# ----------------------------------------------------------------------
# 
# Any constants you want to define.
# 

[CONSTANT]

# the base for all hrefs (e.g., '/~user')
;HREF_BASE = /
;DOCUMENT_ROOT = /var/www/htdocs/
;HTTP_HOST = www.example.com


# ----------------------------------------------------------------------
#
# Yawp::start() configuration options.
# 

[Yawp]

# scripts to execute on login
;login = /path/to/script1.php
;login = /path/to/script2.php

# scripts to execute on logout
;logout = /path/to/script1.php
;logout = /path/to/script2.php

# scripts to execute on authentication wrong-login, idle, or expire
;authErr = /path/to/script1.php
;authErr = /path/to/script2.php

# scripts to execute after full start on each page load
;start = /path/to/script1.php
;start = /path/to/script2.php

# scripts to execute before full stop on each page load
;stop = /path/to/script1.php
;stop = /path/to/script2.php

# error reporting level
error_reporting = %E_ALL%

# session parameters
session_start    = true
session_lifetime = 0
session_path     = %HREF_BASE%
session_domain   = %HTTP_HOST%
session_secure   = false


# ----------------------------------------------------------------------
#
# PEAR Var_Dump options.
# 

[Var_Dump]
display_mode = XHTML_Table


# ----------------------------------------------------------------------
#
# Use this group to turn on the script timer, remove or comment it to
# turn off the timer.  No options are needed or supported.
# 

[Benchmark_Timer]
; no other elements or options needed


# ----------------------------------------------------------------------
#
# PEAR Auth options.  If you set a container, be sure to set another
# group with the [Auth_container] options.  E.g., if you set the 
# container to DB, add an [Auth_DB] group with its options.
#

;[Auth]
;container = DB
;idle = 1800
;expire = 3600
;%AUTH_IDLED% = Your session has been idle for too long.  Please sign in again.
;%AUTH_EXPIRED% = Your session has expired.  Please sign in again.
;%AUTH_WRONG_LOGIN% = You provided an incorrect username or password.  Please try again.

# DB container options for Auth
;[Auth_DB]
;dsn = phptype://username:password@localhost/database
;table = users
;usernamecol = username
;passwordcol = passwd


# ----------------------------------------------------------------------
#
# PEAR Var_Dump options.
# 

[Var_Dump]
display_mode = XHTML_Table


# ----------------------------------------------------------------------
#
# PEAR Cache_Lite options.  See the Cache_Lite docs for more option key
# names.
#

;[Cache_Lite]
;cacheDir = /tmp/
;caching = true
;lifeTime = 3600


# ----------------------------------------------------------------------
#
# PEAR DB DSN and options.
#

;[DB]
;phptype = mysql
;dbsyntax = 
;protocol = 
;hostspec = localhost
;database = the_database
;username = your_username
;password = your_password
;proto_opts = 
;option = 

;[DB_Options]
;debug =
;portability = %DB_PORTABILITY_ALL%


# ----------------------------------------------------------------------
#
# PEAR Log options.  See the Log docs for more handler types.
#

;[Log]
;handler = file
;name = /tmp/yawp.log
;level = %PEAR_LOG_DEBUG%

;[Log]
;handler = mail
;name = user@example.com
;level = %PEAR_LOG_WARNING%


# ----------------------------------------------------------------------
#
# Example: options specific to your web site or application.
#

;[MyWebApp]
;my_key = My Value
