<?php

/*
	This file is part of the Legal Case Management System (LCM).
	(C) 2004-2005 Free Software Foundation, Inc.

	This program is free software; you can redistribute it and/or modify it
	under the terms of the GNU General Public License as published by the
	Free Software Foundation; either version 2 of the License, or (at your
	option) any later version.

	This program is distributed in the hope that it will be useful, but
	WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
	or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
	for more details.

	You should have received a copy of the GNU General Public License along
	with this program; if not, write to the Free Software Foundation, Inc.,
	59 Temple Place, Suite 330, Boston, MA  02111-1307, USA

	$Id: author_det.php,v 1.25 2005/08/18 22:53:11 mlutfy Exp $
*/

include('inc/inc.php');
include_lcm('inc_contacts');
include_lcm('inc_acc');
include_lcm('inc_keywords');

function get_date_range_fields() {
	$ret = array();

	// By default, show from "now() - 1 month" to NOW().
	$link = new Link();
	$link->delVar('date_start_day');
	$link->delVar('date_start_month');
	$link->delVar('date_start_year');
	$link->delVar('date_end_day');
	$link->delVar('date_end_month');
	$link->delVar('date_end_year');
	$ret['html'] =  $link->getForm();

	$ret['html'] .= "<p class=\"normal_text\">\n";
	$ret['date_end'] = get_datetime_from_array($_REQUEST, 'date_end', 'end', "-1");
	$ret['date_start'] = get_datetime_from_array($_REQUEST, 'date_start', 'start',
					date('Y-m-d H:i:s', strtotime("-1 month" . ($ret['date_end'] != "-1" ? $ret['date_end'] : date('Y-m-d H:i:s')))));

	$ret['html'] .= _Ti('time_input_date_start');
	$ret['html'] .= get_date_inputs('date_start', $ret['date_start']);

	$ret['html'] .= _Ti('time_input_date_end');
	if ($ret['date_end'] == "-1")
		$ret['html'] .= get_date_inputs('date_end');
	else
		$ret['html'] .= get_date_inputs('date_end', $ret['date_end']);

	$ret['html'] .= ' <button name="submit" type="submit" value="submit" class="simple_form_btn">'
				. _T('button_validate') 
				. "</button>\n";

	$ret['html'] .= "</p>\n";
	$ret['html'] .= "</form>\n";

	return $ret;
}

global $prefs;
$author = intval($_REQUEST['author']);

if (! ($author > 0)) {
	header("Location: listauthors.php");
	exit;
}

// Get author data
$q = "SELECT *
		FROM lcm_author
		WHERE id_author = $author";
$result = lcm_query($q);

	if ($author_data = lcm_fetch_array($result)) {
		$fullname = get_person_name($author_data);
		lcm_page_start(_T('title_author_view') . ' ' . $fullname, '', '', 'authors_intro');

		// Show tabs
		if ($author == $author_session['id_author'] || $author_session['status'] == 'admin') {
			$groups = array(
				'general' => array('name' => _T('generic_tab_general'),
								'tooltip' => _T('generic_subtitle_general', array('author' => $fullname))),
				'cases' => array('name' => _T('generic_tab_cases'),
								'tooltip' => _T('author_subtitle_cases', array('author' => $fullname))),
				'followups' => array('name' => _T('generic_tab_followups'),
								'tooltip' => _T('author_subtitle_followups', array('author' => $fullname))),
				'appointments' => array('name' => _T('generic_tab_agenda'), 
								'tooltip' => _T('author_subtitle_appointments', array('author' => $fullname))),
				'times' => array('name' => _T('generic_tab_reports'),
								'tooltip' => _T('author_subtitle_reports', array('author' => $fullname)))); 
				// 'attachments' => _T('generic_tab_documents'));
		} else {
			$groups = array(
				'general' => array('name' => _T('generic_tab_general'),
								'tooltip' => _T('generic_subtitle_general', array('author' => $fullname))),
				'cases' => array('name' => _T('generic_tab_cases'),
								'tooltip' => _T('author_subtitle_cases', array('author' => $fullname))));
		}

		$tab = ( isset($_GET['tab']) ? $_GET['tab'] : 'general' );

		// [ML] $_SERVER['REQUEST_URI']);
		// [ML] Forcing 'author_det.php' else some vars really get carried for nothing (see fu tab + dates)
		show_tabs($groups,$tab, "author_det.php?author=$author"); 

		echo '<fieldset class="info_box">';

		switch ($tab) {
			//
			// General tab
			//
			case 'general' :
				//
				// Show client general information
				//
				show_page_subtitle(_T('generic_subtitle_general'), 'authors_intro');

				echo '<p class="normal_text">';
				echo _Ti('authoredit_input_id') . $author_data['id_author'] . "<br />\n";
				echo _Ti('person_input_name') . get_person_name($author_data) . "<br />\n";
				echo _Ti('authoredit_input_status') . _T('authoredit_input_status_' . $author_data['status']) . "<br />\n";
				echo _Ti('time_input_date_creation') . format_date($author_data['date_creation']) . "<br />\n";

				echo "</p>\n";
				
				// Show author contacts (if any)
				show_all_contacts('author', $author_data['id_author']);


				//
				// Show 'edit author' button, if allowed
				//
				if (($GLOBALS['author_session']['status'] == 'admin') ||
					($author == $GLOBALS['author_session']['id_author']))
						echo '<p class="normal_text"><a href="edit_author.php?author=' . $author . '" class="edit_lnk">'
							. _T('authoredit_button_edit') . "</a></p>\n";

				break;
			//
			// Cases tab
			//
			case 'cases':
				// Show recent cases
				show_page_subtitle(_T('author_subtitle_cases', array('author' => get_person_name($author_data)), 'cases_participants'));

				$foo = get_date_range_fields();
				echo $foo['html'];
				
				$q = "SELECT c.id_case, title, date_creation, id_court_archive, status
						FROM lcm_case_author as a, lcm_case as c
						WHERE id_author = " . $author . "
							AND a.id_case = c.id_case 
							AND UNIX_TIMESTAMP(date_creation) >= UNIX_TIMESTAMP('" . $foo['date_start'] . "') ";

				if ($foo['date_end'] != "-1")
					$q .= " AND UNIX_TIMESTAMP(date_creation) <= UNIX_TIMESTAMP('" . $foo['date_end'] . "')";

				// If user is looking at other user, show only public cases
				if (! allowed_author($author, 'r'))
					$q .= " AND c.public = 1";

				// Sort cases by creation date
				$case_order = 'DESC';
				if (isset($_REQUEST['case_order']))
					if ($_REQUEST['case_order'] == 'ASC' || $_REQUEST['case_order'] == 'DESC')
						$case_order = $_REQUEST['case_order'];
				
				$q .= " ORDER BY date_creation " . $case_order;
		
				$result = lcm_query($q);
				$number_of_rows = lcm_num_rows($result);
				$list_pos = 0;
				
				if (isset($_REQUEST['list_pos']))
					$list_pos = $_REQUEST['list_pos'];
				
				if ($list_pos >= $number_of_rows)
					$list_pos = 0;
				
				// Position to the page info start
				if ($list_pos > 0)
					if (!lcm_data_seek($result,$list_pos))
						lcm_panic("Error seeking position $list_pos in the result");

				if (lcm_num_rows($result)) {
					echo "<p class=\"normal_text\">\n";
					show_listcase_start();
		
					for ($cpt = 0; $row1 = lcm_fetch_array($result); $cpt++) {
						show_listcase_item($row1, $cpt);
					}

					show_listcase_end($list_pos, $number_of_rows);
					echo "</p>\n";
				}

				break;
			//
			// Author followups
			//
			case 'followups':
				if (! allowed_author($author, 'r'))
					die("Access denied");
			
				show_page_subtitle(_T('author_subtitle_followups', array('author' => get_person_name($author_data))), 'cases_followups');

				$foo = get_date_range_fields();

				$date_start = $foo['date_start'];
				$date_end   = $foo['date_end'];

				echo $foo['html'];

				echo "<p class=\"normal_text\">\n";
				show_listfu_start('author');
			
				$q = "SELECT id_followup, id_case, date_start, date_end, type, description, case_stage, hidden
					FROM lcm_followup
					WHERE id_author = $author
					  AND UNIX_TIMESTAMP(date_start) >= UNIX_TIMESTAMP('" .  $date_start . "') ";

				if ($date_end != "-1")
					$q .= " AND UNIX_TIMESTAMP(date_end) <= UNIX_TIMESTAMP('" . $date_end . "')";
			
				// Add ordering
				if ($fu_order)
					$q .= " ORDER BY date_start $fu_order, id_followup $fu_order";
			
				$result = lcm_query($q);

				// Check for correct start position of the list
				$number_of_rows = lcm_num_rows($result);
				$list_pos = 0;
				
				if (isset($_REQUEST['list_pos']))
					$list_pos = $_REQUEST['list_pos'];

				if (is_numeric($list_pos)) {
					if ($list_pos >= $number_of_rows)
						$list_pos = 0;
				
					// Position to the page info start
					if ($list_pos > 0)
						if (!lcm_data_seek($result,$list_pos))
							lcm_panic("Error seeking position $list_pos in the result");
				
					$show_all = false;
				} elseif ($list_pos == 'all') {
					$show_all = true;
				}
			
				// Process the output of the query
				// [ML] I don't know if I'm drinking too much coffee, but "$list_pos == 'all'" would always return 1
				for ($i = 0; (($i < $prefs['page_rows']) || $show_all) && ($row = lcm_fetch_array($result)); $i++)
					show_listfu_item($row, $i, 'author');

				show_list_end($list_pos, $number_of_rows, true);
				echo "</p>\n";

				// Total hours for period
				$q = "SELECT sum(IF(UNIX_TIMESTAMP(date_end) > UNIX_TIMESTAMP(date_start), 
								UNIX_TIMESTAMP(date_end)-UNIX_TIMESTAMP(date_start), 0)) as total_time
					FROM lcm_followup
					WHERE id_author = $author
				 	GROUP BY id_author";

				$result = lcm_query($q);
				$row = lcm_fetch_array($result);
				
				echo '<p class="normal_text">';
				echo _Ti('generic_input_total')
					. format_time_interval($row['total_time'], true)
					. " " . _T('time_info_short_hour')
					. "<br />\n";
				echo "</p>\n";
				
				break;
			//
			// Time spent on case by authors
			//
			case 'times' :
				if (! allowed_author($author, 'r'))
					die("Access denied");

				// List time spent for each case
				// Show table headers
				show_page_subtitle(_T('author_subtitle_reports', array('author' => get_person_name($author_data))), 'reports_intro');

				function show_report_for_user($author, $date_start, $date_end, $type) {
					if ($type == "case") {
						$q = "SELECT c.title, c.id_case, 
								sum(IF(UNIX_TIMESTAMP(fu.date_end) > 0,
									UNIX_TIMESTAMP(fu.date_end)-UNIX_TIMESTAMP(fu.date_start), 0)) as time,
								sum(sumbilled) as sumbilled 
						 	  FROM lcm_case as c, lcm_followup as fu 
							  WHERE fu.id_case = c.id_case AND fu.id_author = $author
								AND UNIX_TIMESTAMP(date_start) >= UNIX_TIMESTAMP('" . $date_start . "') ";

						if ($date_end != "-1") 
							$q .= " AND UNIX_TIMESTAMP(date_end) <= UNIX_TIMESTAMP('" . $date_end . "')";

						$q .= " GROUP BY fu.id_case";
					} elseif ($type == "fu") {
						$q = "SELECT fu.type,
								sum(IF(UNIX_TIMESTAMP(fu.date_end) > 0,
									UNIX_TIMESTAMP(fu.date_end)-UNIX_TIMESTAMP(fu.date_start), 0)) as time,
								sum(sumbilled) as sumbilled 
						 	  FROM lcm_followup as fu 
							  WHERE fu.id_author = $author
								AND UNIX_TIMESTAMP(date_start) >= UNIX_TIMESTAMP('" . $date_start . "') ";

						if ($date_end != "-1")
							$q .= " AND UNIX_TIMESTAMP(date_end) <= UNIX_TIMESTAMP('" . $date_end . "') ";

						$q .= " GROUP BY fu.type";
					} elseif ($type == "agenda") {
						$q = "SELECT ap.type,
								sum(IF(UNIX_TIMESTAMP(ap.end_time) > 0,
									UNIX_TIMESTAMP(ap.end_time)-UNIX_TIMESTAMP(ap.start_time), 0)) as time
						 	  FROM lcm_app as ap
							  WHERE ap.id_author = $author
							  	AND ap.id_case = 0
								AND UNIX_TIMESTAMP(start_time) >= UNIX_TIMESTAMP('" . $date_start . "') ";

						if ($date_end != "-1")
							$q .= " AND UNIX_TIMESTAMP(end_time) <= UNIX_TIMESTAMP('" . $date_end . "') ";

						$q .= " GROUP BY ap.type";
					}

					$result = lcm_query($q);

					echo "<p class=\"normal_text\">\n";
					echo "<table border='0' class='tbl_usr_dtl' width='99%'>\n";
					echo "<tr>\n";

					echo '<th class="heading">'
						. _T('case_subtitle_times_by_' . $type)
						. "</th>\n";

					echo "<th class='heading' width='1%' nowrap='nowrap'>" 
						. _Th('case_input_total_time') . ' (' . _T('time_info_short_hour') . ")"
						. "</th>\n";

					$total_time = 0;
					$total_sum_billed = 0.0;

					$meta_sum_billed = (read_meta('fu_sum_billed') == 'yes');
					$meta_sum_billed &= ($type == "case" || $type == "fu");

					if ($meta_sum_billed) {
						$currency = read_meta('currency');
						echo "<th class='heading' width='1%' nowrap='nowrap'>" . _Th('fu_input_sum_billed') . ' (' . $currency . ")</th>\n";
					}

					echo "</tr>\n";

					// Show table contents & calculate total
					while ($row = lcm_fetch_array($result)) {
						echo "<tr>\n";
						echo "<!-- Total = " . $total_sum_billed . " - row = " . $row['sumbilled'] . " -->\n";
	
						$total_time += $row['time'];
						$total_sum_billed += $row['sumbilled'];
	
						echo '<td>';
						
						if ($type == "case") {
							echo '<a class="content_link" href="case_det.php?case=' . $row['id_case'] . '">'
								.  $row['title'] 
								. '</a>';
						} elseif ($type == "fu") {
							echo  _Tkw("followups", $row['type']);
						} elseif ($type == "agenda") {
							echo _Tkw("appointments", $row['type']);
						}
						
						echo '</td>';

						echo '<td align="right">'
						. format_time_interval_prefs($row['time'])
						. "</td>\n";
	
						if ($meta_sum_billed) {
							echo '<td align="right">';
							echo format_money($row['sumbilled']);
							echo "</td>\n";
						}
	
						echo "</tr>\n";
					}

					// Show total case hours
					echo "<tr>\n";
					echo "<td><strong>" . _Ti('generic_input_total') . "</strong></td>\n";
					echo "<td align='right'><strong>";
					echo format_time_interval_prefs($total_time);
					echo "</strong></td>\n";

					if ($meta_sum_billed) {
						echo '<td align="right"><strong>';
						echo format_money($total_sum_billed);
						echo "</strong></td>\n";
					}

					echo "</tr>\n";
					echo "</table>\n";
					echo "</p>\n";
				}
				
				$foo = get_date_range_fields();
				echo $foo['html'];

				show_report_for_user($author, $foo['date_start'], $foo['date_end'], 'case');
				show_report_for_user($author, $foo['date_start'], $foo['date_end'], 'fu');
				show_report_for_user($author, $foo['date_start'], $foo['date_end'], 'agenda');

				break;

			case 'appointments':
				if (! allowed_author($author, 'r'))
					die("Access denied");

				show_page_subtitle(_T('author_subtitle_appointments', array('author' => get_person_name($author_data))), 'tools_agenda');

				$foo = get_date_range_fields();

				$date_start = $foo['date_start'];
				$date_end   = $foo['date_end'];

				echo $foo['html'];

				echo "<p class=\"normal_text\">\n";

				$q = "SELECT ap.*
					FROM lcm_author_app as aa, lcm_app as ap
					WHERE aa.id_app = ap.id_app
						AND UNIX_TIMESTAMP(start_time) >= UNIX_TIMESTAMP('" . $date_start . "') ";

				if ($date_end != "-1") 
					$q .= " AND UNIX_TIMESTAMP(end_time) <= UNIX_TIMESTAMP('" . $date_end . "') ";

				$q .= " AND aa.id_author = " . $GLOBALS['author_session']['id_author'];
				
				// Sort agenda by date/time of the appointments
				$order = 'DESC';
				if (isset($_REQUEST['order']))
					if ($_REQUEST['order'] == 'ASC' || $_REQUEST['order'] == 'DESC')
						$order = $_REQUEST['order'];
				
				$q .= " ORDER BY start_time " . $order;
				
				$result = lcm_query($q);
				
				// Get the number of rows in the result
				$number_of_rows = lcm_num_rows($result);
				if ($number_of_rows) {
					$headers = array( array( 'title' => _Th('time_input_date_start'), 'order' => 'order', 'default' => 'DESC'),
							array( 'title' => ( ($prefs['time_intervals'] == 'absolute') ? _Th('time_input_date_end') : _Th('time_input_duration') ), 'order' => 'no_order'),
							array( 'title' => _Th('app_input_type'), 'order' => 'no_order'),
							array( 'title' => _Th('app_input_title'), 'order' => 'no_order'));
							// array( 'title' => _Th('app_input_reminder'), 'order' => 'no_order'));
					show_list_start($headers);
				
					// Check for correct start position of the list
					$list_pos = 0;
					
					if (isset($_REQUEST['list_pos']))
						$list_pos = $_REQUEST['list_pos'];
					
					if ($list_pos>=$number_of_rows) $list_pos = 0;
					
					// Position to the page info start
					if ($list_pos>0)
						if (!lcm_data_seek($result,$list_pos))
							lcm_panic("Error seeking position $list_pos in the result");
					
					// Show page of the list
					for ($i = 0 ; (($i<$prefs['page_rows']) && ($row = lcm_fetch_array($result))) ; $i++) {
						echo "\t<tr>";
						echo '<td class="tbl_cont_' . ($i % 2 ? 'dark' : 'light') . '">'
							. format_date($row['start_time'], 'short') . '</td>';
				
						echo '<td class="tbl_cont_' . ($i % 2 ? 'dark' : 'light') . '">'
							. ( ($prefs['time_intervals'] == 'absolute') ?
								format_date($row['end_time'], 'short') :
								format_time_interval(strtotime($row['end_time']) - strtotime($row['start_time']),
											($prefs['time_intervals_notation'] == 'hours_only') )
							) . '</td>';
						echo '<td class="tbl_cont_' . ($i % 2 ? 'dark' : 'light') . '">' . _Tkw('appointments', $row['type']) . '</td>';
						echo '<td class="tbl_cont_' . ($i % 2 ? 'dark' : 'light') . '">'
							. '<a href="app_det.php?app=' . $row['id_app'] . '" class="content_link">' . $row['title'] . '</a></td>';
						// [ML] removed, not very useful.
						// echo '<td class="tbl_cont_' . ($i % 2 ? 'dark' : 'light') . '">'
						//	. format_date($row['reminder'], 'short') . '</td>';
						echo "</tr>\n";
					}
				
					show_list_end($list_pos, $number_of_rows);
				}
				
				echo "</p>\n";

				echo '<p><a href="edit_app.php?app=0" class="create_new_lnk">' . _T('app_button_new') . '</a></p>';

				break;
	
			/*
			case 'attachments':
				echo "Not yet implemented";
				
				// Should show all attached documents by author
				// but not allow to upload

				break;
			*/
		}

		echo "</fieldset>\n";
		lcm_page_end();
	} else {
		die("There's no such author!");
	}

?>
