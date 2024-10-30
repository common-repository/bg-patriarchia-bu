<?php
/* 
    Plugin Name: Bg Patriarchia BU 
    Plugin URI: https://bogaiskov.ru/bg_pbu/
    Description: Plugin copies liturgical guides from the Patriarchia.ru and inserts them into a page on your site.
    Version: 2.2.3
    Author: VBog
    Author URI: https://bogaiskov.ru 
	License:     GPL2
	GitHub Plugin URI: https://github.com/VBog/bg-patriarchia-bu
*/

/*  Copyright 2018-2023  Vadim Bogaiskov  (email: vadim.bogaiskov@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*****************************************************************************************
	Блок загрузки плагина
	
******************************************************************************************/

// Запрет прямого запуска скрипта
if ( !defined('ABSPATH') ) {
	die( 'Sorry, you are not allowed to access this page directly.' ); 
}

define('BG_PBU_VERSION', '2.2.3');

// Таблица стилей для плагина
function bg_pbu_enqueue_frontend_styles () {
	wp_enqueue_style( "bg_pbu_styles", plugins_url( '/css/pbu.css', plugin_basename(__FILE__) ), array() , BG_PBU_VERSION  );
}
add_action( 'wp_enqueue_scripts' , 'bg_pbu_enqueue_frontend_styles' );

if ( defined('ABSPATH') && defined('WPINC') ) {
// Регистрируем крючок для обработки контента при его загрузке
	add_filter( 'the_content', 'bg_pbu_proc', 1 );
}
// Вставляет шорт-код [patriarchiaBU] с приоритетом 1
function bg_pbu_proc($content) {
	if ( has_shortcode( $content, 'patriarchiaBU' ) ) {
		$pattern = get_shortcode_regex( array('patriarchiaBU') );
		if ( preg_match_all( '/'. $pattern .'/s', $content, $matches )
			&& array_key_exists( 2, $matches )
			&& in_array( 'patriarchiaBU', $matches[2] )
		) {
			foreach ($matches[0] as $mtch) {
				$content = str_replace ( $mtch, do_shortcode($mtch), $content );
			}
		}
	}
	return $content;
}
// Функция, исполняемая при деактивации  плагина
function bg_pbu_deactivate() {
	bg_pbu_unschedule();
	delete_option('bg_pbu_options_2_1_3');
}
register_deactivation_hook(  __FILE__, 'bg_pbu_deactivate' );

// Функция, исполняемая при удалении плагина
function bg_pbu_uninstall() {
	$upload_dir = wp_upload_dir();
	$storage = $upload_dir['basedir'] ."/bg_pbu";
	bg_pbu_removeDirectory($storage);
	delete_option('bg_pbu_options');
	delete_option('bg_pbu_options_2_1_3');
	bg_pbu_unschedule();
	remove_filter( 'cron_schedules', 'bg_pbu_cron_action' );
}
function bg_pbu_removeDirectory($dir) {
	if ($objs = glob($dir."/*")) {
		foreach($objs as $obj) {
			is_dir($obj) ? removeDirectory($obj) : unlink($obj);
		}
	}
	rmdir($dir);
}


// Сброс всех расписаний
function bg_pbu_unschedule() {
	for ($val = 0; $val < 4; $val++) {
		$schedule_option = array(false, $val);
		while( false !== ( $time = wp_next_scheduled( 'bg_pbu_cron_action', $schedule_option ) ) ) { 
			wp_unschedule_event( $time, 'bg_pbu_cron_action', $schedule_option ); 
		}
		$schedule_option = array(true, $val);
		while( false !== ( $time = wp_next_scheduled( 'bg_pbu_cron_action', $schedule_option ) ) ) { 
			wp_unschedule_event( $time, 'bg_pbu_cron_action', $schedule_option ); 
		}
	}
	if ( false !== ( $time = wp_next_scheduled( 'bg_pbu_cron_action' ) ) ) { 
		wp_unschedule_event( $time, 'bg_pbu_cron_action' ); 
	}
}

register_uninstall_hook(__FILE__, 'bg_pbu_uninstall');

include_once( 'inc/upload.php' );
include_once( 'inc/options.php' );

/*****************************************************************************************
	Шорт-коды
	Функции обработки шорт-кодов
******************************************************************************************/
//  [patriarchiaBU]
add_shortcode( 'patriarchiaBU', 'bg_pbu_shortcode' );
// Функция обработки шорт-кода patriarchiaBU
function bg_pbu_shortcode( $atts ) {
	extract( shortcode_atts( array(
		'date' => '',		// Дата в формате YYYY-mm-dd
		'hlink' => 'off' 	// Отключены внешние ссылки
	), $atts ) );
	if ($date && ($date[0] == '-' || $date[0] == '+')) {
		$dd = (int)$date;
		$date = date('Y-m-d', bg_pbu_add_days(time(), $dd));
	}
	if (!strtotime ($date)) {
		if (isset($_GET["date"]))$date = $_GET["date"];
		if (!strtotime ($date)) $date = date('Y-m-d');
	}
	
	$quote = bg_pbu_parseBU( $date, $hlink );
	return "{$quote}";
}
//  [the_nextday]
if ( !shortcode_exists( 'the_nextday' ) ) {
	add_shortcode( 'the_nextday', 'bg_pbu_nextday' );
	// Функция обработки шорт-кода next_day
	function bg_pbu_nextday($atts) {
		extract( shortcode_atts( array(
			'title' => 'Следующий день ▶'	// Подпись на кнопке
		), $atts ) );
		$option = bg_pbu_get_option();
		
		$y = date ("Y", mktime ( 0, 0, 0 ));	// Текущий год
		if (isset($_GET['date'])) {
			$dd = $_GET["date"];
			list($year, $month, $day) = explode("-",$dd);
			if ($year < $y-$option['years_before'] || $year > $y+$option['years_after']) return "";	// Генерировать ссылку только для разрешенного диапазона
			$d = date ("U", mktime ( 0, 0, 0, $month, $day, $year ));
		} else {
			$d = date ("U", mktime ( 0, 0, 0 ));
		}
		$selected = date('?\d\a\t\e=Y-m-d', bg_pbu_add_days($d, 1));
		$title = str_replace('%date%', bg_pbu_rusDate($d), $title);
		$input = '<a class="bg_pbu_nextday" href="'. get_page_link( ) . $selected. '" title="'.$title.'" rel="nofollow">'.$title.'</a>'; 
		return "{$input}"; 
		
	}
}
//  [the_prevday]
if ( !shortcode_exists( 'the_prevday' ) ) {
	add_shortcode( 'the_prevday', 'bg_pbu_prevday' );
	// Функция обработки шорт-кода prev_day
	function bg_pbu_prevday($atts) {
		extract( shortcode_atts( array(
			'title' => '◀ Предыдущий день'	// Подпись на кнопке
		), $atts ) );
		$option = bg_pbu_get_option();
		
		$y = date ("Y", mktime ( 0, 0, 0 ));	// Текущий год
		if (isset($_GET['date'])) {
			$dd = $_GET["date"];
			list($year, $month, $day) = explode("-",$dd);
			if ($year < $y-$option['years_before'] || $year > $y+$option['years_after']) return "";	// Генерировать ссылку только для разрешенного диапазона
			$d = date ("U", mktime ( 0, 0, 0, $month, $day, $year ));
		} else {
			$d = date ("U", mktime ( 0, 0, 0 ));
		}
		$selected = date('?\d\a\t\e=Y-m-d', bg_pbu_add_days($d, (-1)));
		$title = str_replace('%date%', bg_pbu_rusDate($d), $title);
		$input = '<a class="bg_pbu_prevday" href="'. get_page_link( ) . $selected. '" title="'.$title.'" rel="nofollow">'.$title.'</a>'; 
		return "{$input}"; 
		
	}
}
//  [datelink]
if ( !shortcode_exists( 'datelink' ) ) {
	add_shortcode( 'datelink', 'bg_pbu_datelink' );
	// Функция обработки шорт-кода datelink
	function bg_pbu_datelink($atts) {
		extract( shortcode_atts( array(
			'title' => 'Перейти к календарю',	// Подпись на кнопке
			'url' => '',
			'target' => '_blank'
		), $atts ) );
		
		if (!$url) return ""; 
		if (isset($_GET['date'])) {
			$d = $_GET["date"];
		} else {
			$d = date ("Y-m-d", time( ));
		}
		$title = str_replace('%date%', bg_pbu_rusDate($d), $title);
		$input = '<a class="bg_pbu_datelink" href="'. $url . $d. '" title="'.$title.'" target="'.$target.'">'.$title.'</a>'; 
		return "{$input}"; 
		
	}
}

/*****************************************************************************************
	Функции считывает загруженный ранее с сайта patriarchia.ru файл с БУ 
	и делает необходимые преобразования текста:
	- заменяет заголовок
	- удаляет внешние ссылки
	- преобразует ссылки на Библию к стандартному виду
******************************************************************************************/
function bg_pbu_parseBU( $date, $hlink ) {
	
	$quote = "";
	$option = bg_pbu_get_option();
	
	$cur_year = Date ('Y');
	$year = Date ('Y', strtotime($date))+0;
	if ($year < $cur_year-$option['years_before'] || $year > $cur_year+$option['years_after']) 
		return	"Богослужебные указания за ".bg_pbu_rusDate($date)." г. не доступны.<br>Допустимый диапазон лет: от ".($cur_year-$option['years_before']).
				" до ".($cur_year+$option['years_after'])." гг. включительно.";
				
	$key = 'bg_pbu_parseBU-'.$date.'-'.$hlink;
	$quote = get_transient($key);
//	if (false ===$quote) {
		$doc = new DOMDocument();
		
		$upload_dir = wp_upload_dir();
		$file = $upload_dir['basedir'] ."/bg_pbu/bu_".$date;

		// Поищем файл у себя в загашнике
		if (!@$doc->loadHTMLFile($file)) return "Богослужебные указания за ".bg_pbu_rusDate($date)." г. отсутствуют.";

		$elements = $doc->getElementsByTagName('div');
		foreach ($elements as $tag) {

			// Находим на странице <div class="main" id="main">...</div> - блок содержаший Богослужебные указания 	
			if ($tag->getAttribute('class') == 'main') {
			// Похоже мы взяли документ с   http://www.patriarchia.ru/bu/

				// Проверяем дату данных в файле
				$h1s = $doc->getElementsByTagName('h1');			// Заголовок должен содержать требуемую дату на русском языке
				foreach ($h1s as $h1) {
					if (stripos($h1->nodeValue, bg_pbu_rusDate($date)) !== false) {
						if (!file_exists($file)) 
							$doc->saveHTMLFile($file);					// Сохраним скаченную страницу в загашнике
						$tag->removeAttribute('id');					// Удаляем атрибут id = "main"
						$tag->setAttribute('class', "patriarchiaBU");	// Изменяем название класса
						$quote = $doc->saveHTML($tag)."\n";
						// Заменяем уровень заголовка с h1 на h3
						$quote = str_ireplace("h1>","h3>", $quote);
						break 2;										// Всё нашли и сохранили, завершаем работу
					}
				}
			}	
		}
		if (!$quote) {
			// Удаляем файл с ошибками
			if (file_exists($file)) unlink ( $file );
			return "Богослужебные указания за ".bg_pbu_rusDate($date)." г. не найдены.";
		}
		if ($hlink != 'on') {
		// Удаляем все внешние ссылки	
			$elements = $doc->getElementsByTagName('a');
			foreach ($elements as $tag) {
				$href = $tag->getAttribute('href');
				if ($href[0] != "#") {
					$tag_a = $doc->saveHTML($tag);
					$txt = $tag->nodeValue;
					$quote = str_ireplace($tag_a, $txt, $quote);
				}
			}
		// Преобразуем ссылки на Библию к стандартному виду
			// Удаляем зачала
			$quote = preg_replace ('/,\s*\d+\s*зач\.\s*(\(от полу́\\))?,/i','', $quote);	
			// Заменяем римские цифры на арабские
			$quote = preg_replace_callback ('/\s*([MDCLXVI]+),\s*/i', 
				function($matches){
					$chapter = bg_pbu_roman2dec ($matches[1]);
					return $chapter.":";
				}, $quote);	
			$quote = preg_replace ('/Прем\.\sСолом/i','ПремСол', $quote);	
			$quote = preg_replace ('/(\d)_Цар/i','$1 Цар', $quote);	
			$quote = preg_replace ('/(\d)_Пар/i','$1 Пар', $quote);	
			$quote = preg_replace ('/(\d)_Езд/i','$1 Езд', $quote);	
			$quote = preg_replace ('/(\d)_Мак/i','$1 Мак', $quote);	
			$quote = preg_replace ('/(\d)_Пет/i','$1 Пет', $quote);	
			$quote = preg_replace ('/(\d)_Ин/i','$1 Ин', $quote);	
			$quote = preg_replace ('/(\d)_Кор/i','$1 Кор', $quote);	
			$quote = preg_replace ('/(\d)_Фес/i','$1 Фес', $quote);	
		}
		set_transient( $key, $quote, $option['cache'] );
//	}
	$quote .= '<p class="vd"><a href="http://www.patriarchia.ru/bu/'.$date.'" target="_blank">Богослужебные указания с сайта Патриархии на каждый день.</a></p>';
	return $quote;
}

/*****************************************************************************************
	Преобразование римских чисел в арабские
	
******************************************************************************************/
function bg_pbu_roman2dec($text) {
	$font_ar = array(1,4,5,9,10,40,50,90,100,400,500,900,1000,4000,5000,9000,10000);
	$font_rom = array("I","IV","V","IX","X","XL","L","XC","C","CD","D","CM","M",
					"M&#8577;","&#8577;","&#8577;&#8578;","&#8578;");
   
	$text = strtoupper($text);
    $text = preg_replace("[^IVXLCDM]", "", $text);  // Removing all not-roman letters 
	$rezult = 0;
	$posit = 0;
	$n = count($font_ar) - 1;
	while ($n >= 0 && $posit < strlen ($text)) {
		if (substr($text, $posit, strlen ($font_rom[$n])) == $font_rom[$n]) {
			$rezult += $font_ar[$n];
			$posit += strlen ($font_rom[$n]);
		}
		else $n--;
	}
	return $rezult;
}

/*****************************************************************************************
	Представление даты на русском языке
	
******************************************************************************************/
function bg_pbu_rusDate($date) {
	$mnr = array(" января "," февраля "," марта "," апреля "," мая "," июня "," июля "," августа "," сентября "," октября "," ноября "," декабря ");
	list($y, $m, $d) = explode('-', $date);
	$d = (int)$d+0;
	$m = (int)$m-1;
	$y = (int)$y+0;
	return $d.$mnr[$m].$y;
}

/*******************************************************************************
	Функция добавляет заданное количество дней к дате, заданной в виде int
*******************************************************************************/  
function bg_pbu_add_days($date, $days) {
	return date( 'U', mktime ( 0, 0, 0, date("n", $date), date("j", $date)+$days, date("Y", $date) ) );
}
