<?php
/*********************************************************************
	Страница настроек плагина
	
**********************************************************************/
add_action('admin_menu', 'add_plugin_page');
function add_plugin_page(){
	add_options_page( 'Настройки плагина Богослужебные указания', 'Богослужебные указания', 'manage_options', 'bg_pbu_slug', 'bg_pbu_options_page_output' );
}

function bg_pbu_options_page_output(){
	?>
	<div class="wrap">
		<h2><?php echo get_admin_page_title() ?></h2>

		<form action="options.php" method="POST">
			<?php
				settings_fields( 'bg_pbu_option_group' );     // скрытые защитные поля
				do_settings_sections( 'bg_pbu_page' ); // секции с настройками (опциями). У нас она всего одна 'section_id'
				submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Регистрируем настройки.
 * Настройки будут храниться в массиве, а не одна настройка = одна опция.
 */
add_action('admin_init', 'bg_pbu_plugin_settings');
function bg_pbu_plugin_settings(){
	$val = bg_pbu_get_option();
	if ($val['update']) {
		// Запускаем функцию bg_pbu_checkBU() сейчас
		wp_schedule_single_event( time(), 'bg_pbu_cron_action', array(true) );
		add_action( 'bg_pbu_cron_action', 'bg_pbu_checkBU' );
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-info is-dismissible">
				<p>Процесс обновления файлов на вашем сервере запущен <?php echo date("Y-m-j H:i"); ?> в фоновом режиме!<br>Это займёт 3-5 минут.</p>
			</div>
			<?php
		});
		$val['update']=0;
		update_option( 'bg_pbu_options', $val );
	}

	// параметры: $option_group, $option_name, $sanitize_callback
	register_setting( 'bg_pbu_option_group', 'bg_pbu_options', 'sanitize_callback' );

	// параметры: $id, $title, $callback, $page
	add_settings_section( 'section_id', 'Общие настройки', '', 'bg_pbu_page' ); 

	// параметры: $id, $title, $callback, $page, $section, $args
	add_settings_field('bg_pbu_field4', 'Диапазон доступности, лет', 'fill_bg_pbu_field4', 'bg_pbu_page', 'section_id' );
	add_settings_field('bg_pbu_field3', 'Обновлять кеш', 'fill_bg_pbu_field3', 'bg_pbu_page', 'section_id' );
//	add_settings_field('bg_pbu_field1', 'Порядок проверки источников', 'fill_bg_pbu_field1', 'bg_pbu_page', 'section_id' );
	add_settings_field('bg_pbu_field5', 'Способ чтения источников данных', 'fill_bg_pbu_field5', 'bg_pbu_page', 'section_id' );
	add_settings_field('bg_pbu_field2', 'Обновить файлы сейчас', 'fill_bg_pbu_field2', 'bg_pbu_page', 'section_id' );
}
## Заполняем опцию 4

function fill_bg_pbu_field4(){
	$val = get_option('bg_pbu_options');
	$val1 = $val ? $val['years_before'] : 5;
	$val2 = $val ? $val['years_after'] : 1;
	?>
	<label>до<input type="number" name="bg_pbu_options[years_before]" value="<?php echo $val1;?>" style="width: 50px;" min=0 /></label>
	<label> после<input type="number" name="bg_pbu_options[years_after]" value="<?php echo $val2; ?>" style="width: 50px;" min=0 /> текщего года</label>
	<?php
}
## Заполняем опцию 3

function fill_bg_pbu_field3(){
	$val = get_option('bg_pbu_options');
	$val = $val ? $val['cache'] : DAY_IN_SECONDS;
	?>
	<select name="bg_pbu_options[cache]">
		<option <?php selected(1, $val); ?> value='1'>мгновенно</option>
		<option <?php selected(HOUR_IN_SECONDS, $val); ?> value=<?php echo HOUR_IN_SECONDS; ?>>каждый час</option>
		<option <?php selected(DAY_IN_SECONDS, $val); ?> value=<?php echo DAY_IN_SECONDS; ?>>ежедневно</option>
		<option <?php selected(WEEK_IN_SECONDS, $val); ?> value=<?php echo WEEK_IN_SECONDS; ?>>еженедельно</option>
		<option <?php selected(MONTH_IN_SECONDS, $val); ?> value=<?php echo MONTH_IN_SECONDS; ?>>ежемесячно</option>
		<option <?php selected(YEAR_IN_SECONDS, $val); ?> value=<?php echo YEAR_IN_SECONDS; ?>>ежегодно</option>
		<option <?php selected(0, $val); ?> value='0'>не обновлять</option>
		
	</select>
	<?php
}


## Заполняем опцию 5
function fill_bg_pbu_field5(){
	$val = get_option('bg_pbu_options');
	$val = $val ? $val['curl'] : 0;
	?>
	<select name="bg_pbu_options[curl]">
		<option <?php selected(0, $val); ?> value='0'>fopen</option>
		<option <?php selected(1, $val); ?> value='1'>curl</option>
	</select>
	<p>Проверьте разрешен ли для функции <b>fopen()</b> доступ к внешним файлам (см. в <b>php.ini</b>: <code>php_value allow_url_fopen On</code>)<br>
	и/или установлен ли пакет <b>CURL</b> на вошем сервере? Выберите соответствующий разрешенный режим.</p>
	<?php
}

## Заполняем опцию 2
function fill_bg_pbu_field2(){
	$val = get_option('bg_pbu_options');
	$time = wp_next_scheduled( 'bg_pbu_cron_action', array(false));
	$val = $val ? $val['update'] : null;
	?>
	<label><input type="checkbox" name="bg_pbu_options[update]" value="1" <?php checked( 1, $val ) ?> /> отметить и нажать "Сохранить изменения"</label>
	<p>(Проверка наличия файлов запланирована: <?php echo $time?date ("d-m-Y H:i T", $time):"<i>не запланировано</i>"; ?>.<br>Обновление файлов не производится!)</p>
	<?php
}

## Очистка данных
function sanitize_callback( $options ){ 
	// очищаем
	foreach( $options as $name => & $val ){
		if( $name == 'years_before' )
			$val = intval( $val );

		if( $name == 'years_after' )
			$val = intval( $val );

		if( $name == 'cache' )
			$val = intval( $val );

		if( $name == 'curl' )
			$val = intval( $val );

		if( $name == 'update' )
			$val = intval( $val );
	}

	return $options;
}

function bg_pbu_get_option() {
	$val = get_option('bg_pbu_options');
	if (!isset($val)) add_option( 'bg_pbu_options', array('years_before'=>5, 'years_after'=>1, 'cache'=>DAY_IN_SECONDS, 'curl'=>0, 'update'=>null) );

	if (!isset($val['years_before'])) $val['years_before'] = 5;
	if (!isset($val['years_after'])) $val['years_after'] = 1;
	if (!isset($val['cache'])) $val['cache'] = DAY_IN_SECONDS;
	if (!isset($val['curl'])) $val['curl'] = 0;
	if (!isset($val['update'])) $val['update'] = null;
	

	update_option ('bg_pbu_options', $val);
	
	return $val;
}

