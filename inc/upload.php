<?php
define( 'BG_PBU_DEBUG_FILE', dirname(__FILE__ )."/pbu%d.log");

/*****************************************************************************************
	Функция проверки наличия новых БУ и отсутствия старых
		$update	=true	- обновить сохраненные файлы (по умолчанию false)
******************************************************************************************/
function bg_pbu_checkBU($update=false) {

	global $bg_pbu_debug_file;
	$upload_dir = wp_upload_dir();
	$storage = $upload_dir['basedir'] ."/bg_pbu";
	$option = bg_pbu_get_option();
	
	// Текущий год
	$Y = date('Y');
	$bg_pbu_debug_file = sprintf(BG_PBU_DEBUG_FILE, $Y);
	@unlink ( sprintf(BG_PBU_DEBUG_FILE, $Y-2) );		// Удаляем позопрошлогодний файл, если есть
	
	$cur_year = Date ('Y');
	$year = Date ('Y', strtotime($date))+0;
	if ($year < $cur_year-$option['years_before'] || $year > $cur_year+$option['years_after']) 
	// Зададим интервал:
	$begin = new DateTime( ($Y-$option['years_before']).'-01-01' );	// с 1 января текущего года
	$end = new DateTime( ($Y+$option['years_after']).'-12-31' );	// до 31 декабря следующего года
	$end = $end->modify( '+1 day' ); 

	$interval = new DateInterval('P1D');
	$daterange = new DatePeriod($begin, $interval ,$end);

	$val = get_option('bg_pbu_options');
	$curl = $val['curl'];
	
	$doc = new DOMDocument();
	$num = 0;
	error_log( PHP_EOL .date("Y-m-d H:i").($update?" Срочно":" По плану"), 3, $bg_pbu_debug_file);
	if (!file_exists($storage)) @mkdir( $storage );
	// Для каждой даты из этого диапазона
	foreach($daterange as $date){
		$d = $date->format("Y-m-d");
		$file = $storage ."/bu_".$d;
		error_log( PHP_EOL ." file: ".$file, 3, $bg_pbu_debug_file);
		// Сначала проверим есть ли сохраненный файл
		if (!file_exists($file) || $update) {
			$page1 = 'http://www.patriarchia.ru/bu/'.$d.'/print.html';
			error_log( PHP_EOL ." Патриархия: ".$page1, 3, $bg_pbu_debug_file);
			
			// Если файла нет, то попытаемся его украсть на Патриархия.Ru
			if ($page1) {
				if ($curl) {
					$data = bg_pbu_curl($page1);
					$status = @$doc->loadHTML($data);
				} else $status = @$doc->loadHTMLFile($page1);
				if ($status) {
					// Проверяем дату данных в файле
					$h1s = $doc->getElementsByTagName('h1');			// Заголовок должен содержать требуемую дату на русском языке
					foreach ($h1s as $h1) {
						if (stripos($h1->nodeValue, bg_pbu_rusDate($d)) !== false) {
							if ($doc->saveHTMLFile($file)) $num++;
							break; 
						}
					}
				} 
			}
			// Если его там нет, то останавливаемся на достигнутом
		}
	}
	error_log( " ".date("Y-m-d H:i")." Всего файлов: " .$num, 3, $bg_pbu_debug_file);
	return $num;
}

// Загружаем содержимое страницы через CURL
function bg_pbu_curl($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

// Запускаем функцию bg_pbu_checkBU() раз в сутки
function bg_pbu_schedule () {
	if ( !wp_next_scheduled( 'bg_pbu_cron_action', array(false)) ) {
		bg_pbu_unschedule();
		wp_schedule_event( time(), 'daily', 'bg_pbu_cron_action', array(false));
	}
	add_action( 'bg_pbu_cron_action', 'bg_pbu_checkBU' );
}

if (!get_option('bg_pbu_options_2_1_3')){
	bg_pbu_unschedule();
	add_option('bg_pbu_options_2_1_3', 1);
}
bg_pbu_schedule ();