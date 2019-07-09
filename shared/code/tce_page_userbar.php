<?php
//============================================================+
// File name   : tce_page_userbar.php
// Begin       : 2004-04-24
// Last Update : 2012-12-30
//
// Description : Display user's bar containing copyright
//               information, user status and language
//               selector.
//
// Author: Nicola Asuni
//
// (c) Copyright:
//               Nicola Asuni
//               Tecnick.com LTD
//               www.tecnick.com
//               info@tecnick.com
//
// License:
//    Copyright (C) 2004-2013 Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * Display user's bar containing copyright information, user status and language selector.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2004-04-24
 */

// IMPORTANT: DO NOT REMOVE OR ALTER THIS PAGE!

// skip links

echo '<div class="minibutton" dir="ltr">';
echo '<span class="copyright"><a href="http://www.tcexam.org">TCExam</a> ver. '.K_TCEXAM_VERSION.' - Copyright &copy; 2004-2018 Nicola Asuni - <a href="http://www.tecnick.com">Tecnick.com LTD</a></span>';
echo '</div>'.K_NEWLINE;

// Display W3C logos
// echo '<div class="minibutton" dir="ltr">'.K_NEWLINE;
// echo '<a href="http://validator.w3.org/check?uri='.K_PATH_HOST.$_SERVER['SCRIPT_NAME'].'" class="minibutton" title="This Page Is Valid XHTML 1.0 Strict!">W3C <span>XHTML 1.0</span></a> <span style="color:white;">|</span>'.K_NEWLINE;
// echo '<a href="http://jigsaw.w3.org/css-validator/" class="minibutton" title="This document validates as CSS!">W3C <span>CSS 2.0</span></a> <span style="color:white;">|</span>'.K_NEWLINE;
// echo '<a href="http://www.w3.org/WAI/WCAG1AAA-Conformance" class="minibutton" title="Explanation of Level Triple-A Conformance">W3C <span>WAI-AAA</span></a>'.K_NEWLINE;
// echo '</div>'.K_NEWLINE;

//============================================================+
// END OF FILE
//============================================================+
