<?
// Модуль "Аккаунт"
// Скачивание товаров xlsx и загрузка
set_time_limit(3600);

if (isset($_POST['action']) && $_POST['action'] == 'upload') {
	// Принимаем файл
	$error == '';
	if (isset($_FILES['file']) && $_FILES['file']['name'] != '' && $_FILES['file']['error']==0 && $_FILES['file']['size']>16 && $_FILES['file']['size'] <= 1048576 && is_file($_FILES['file']['tmp_name'])) {
		$file = $_FILES['file']['tmp_name'];
		$fp = pathinfo($_FILES['file']['name']);
		if ($fp['extension'] != 'xlsx') {
			$error = $arr_words['file_error'];
		}
	} else {
		$error = $arr_words['file_error'];
	}
	if ($error == '') {
		require($_SERVER['DOCUMENT_ROOT'].'/include/PHPExcel/IOFactory.php');
		$objReader = new PHPExcel_Reader_Excel2007();	
		$objReader->setReadDataOnly(true);
		try {
			$objPHPExcel = PHPExcel_IOFactory::load($file);
		} catch(Exception $e) {
			$error = $arr_words['file_error'];
		}
		if ($error == '') {
			$arr_yn = array(mb_strtolower($arr_words['yes'])=>1, mb_strtolower($arr_words['no'])=>0);
			
			// Бренды
			$arr_brands_w = array();
			$sql = "SELECT `id`,".($config['lang'] == '' ? "LOWER(`name`) AS `name`" : "(SELECT LOWER(`name`) FROM `structure_lang` WHERE `s_id`=`structure`.`id` AND `lang`='{$config['lang']}' LIMIT 1) AS `name`") ." FROM `structure` WHERE `module`='catalog' HAVING `name`!='' ORDER BY `name` ASC";
			$res = $mysqli->query($sql);
			while ($row2 = $res->fetch_assoc()) {
				$arr_brands_w[$row2['name']] = $row2['id'];
			}
			// Типы
			$arr_types_w = array(mb_strtolower($arr_words['catalog_type_0'])=>0, mb_strtolower($arr_words['catalog_type_1'])=>1);
			$sql = "SELECT COUNT(*) FROM `catalog` WHERE `u_id`={$_SESSION['account']['id']}";
			$res = $mysqli->query($sql);
			list($count) = $res->fetch_row();
			$updated = 0;
			if ($count > 0) {
				$count++;
				$sheetData = $objPHPExcel->getActiveSheet()->RangeToArray('A2:N'.$count,null,false,false,true);	
				$catalog = array();
				foreach ($sheetData as $v) {
					$id = (int)$v['A'];
					$sql = "SELECT * FROM `catalog` WHERE `id`={$id} AND `u_id`={$_SESSION['account']['id']} LIMIT 1";
					$res = $mysqli->query($sql);
					if ($catalog = $res->fetch_assoc()) {
						$type_w = mb_strtolower(trim($v['B']));
						if (isset($arr_types_w[$type_w])) {
							$catalog['type'] = $arr_types_w[$type_w];
						}
						$brand_w = mb_strtolower(trim($v['C']));
						if (isset($arr_brands_w[$brand_w])) {
							$catalog['brand'] = $arr_brands_w[$brand_w];
						}
						$name = my_str($v['D'],128);
						if ($catalog['name'] != '') {
							$catalog['name'] = $name;
						}
						$only_pcb_w = mb_strtolower(trim($v['E']));
						if (isset($arr_yn[$only_pcb_w])) {
							$catalog['only_pcb'] = $arr_yn[$only_pcb_w];
						}
						$catalog['address'] = my_str($v['F'],64);
						$sn = my_str($v['G'],64);
						if ($sn != '') {
							// Проверяем уникальность серийника
							$sn_esc = $mysqli->real_escape_string($sn);
							$sql = "SELECT `id` FROM `catalog` WHERE `u_id`={$_SESSION['account']['id']} AND `sn`='{$sn_esc}' AND `id`!={$catalog['id']} LIMIT 1";
							$res = $mysqli->query($sql);
							if ($res->num_rows == 1) {
								$sn_esc = $mysqli->real_escape_string($catalog['sn']);
							}		
						} else {
							$sn_esc = '';
						}
						$catalog['pn'] = my_str($v['H'],64);
						$catalog['fw'] = my_str($v['I'],64);
						$catalog['count'] = (int)substr(trim($v['J']), 0 ,8);
						$cost_price = round((float)substr(str_replace(',', '.',trim($v['K'])), 0 ,10), 2);
						$price = round((float)substr(str_replace(',', '.', trim($v['L'])), 0 ,10), 2);
						if ($cost_price <= $price) {
							$catalog['cost_price'] = $cost_price;
							$catalog['price'] = $price;
						}	
						$catalog['info'] = trim(substr(strip_tags(trim($v['N']), '<br><b><strong><i><ul><ol><li><table><tr><td><th><tbody>'), 0, 5000));
						if ($catalog['count'] < 1) {
							$catalog['count'] = 1;
						}
						if ($catalog['price'] <= 0) {
							$catalog['price'] = '0.00';
						}	
						if ($catalog['cost_price'] <= 0) {
							$catalog['cost_price'] = '0.00';
						}
						$for_sale = mb_strtolower(trim($v['M']));
						if (isset($arr_yn[$for_sale])) {
							$catalog['visible'] = $arr_yn[$for_sale];
						}
						
						$sql = "UPDATE `catalog` SET `only_pcb`={$catalog['only_pcb']},`date`=NOW(),`type`={$catalog['type']},`s_id`={$catalog['brand']},`name`='".$mysqli->real_escape_string($catalog['name'])."',`sn`='{$sn_esc}',`pn`='".$mysqli->real_escape_string($catalog['pn'])."',`address`='".$mysqli->real_escape_string($catalog['address'])."',`fw`='".$mysqli->real_escape_string($catalog['fw'])."',`count`={$catalog['count']},`price`={$catalog['price']},`cost_price`={$catalog['cost_price']},`info`='".$mysqli->real_escape_string($catalog['info'])."',`visible`={$catalog['visible']} WHERE `id`={$catalog['id']} AND `u_id`={$_SESSION['account']['id']} LIMIT 1";
						$mysqli->query($sql);
						$updated += $mysqli->affected_rows;
					}		
				}
			}
			$_SESSION['upload_success'] = $updated;
			header('Location: /'.$config['url_all'].'/catalog/download/');
			die();
		}
	}
	if ($error != '') {
		$smarty->assign('error', $error);
	}	
	
} elseif ($config['page'] == 'file') {
	// Отдаём xlsx на скачивание
	$file = 'stockroom.xlsx';
	require($_SERVER['DOCUMENT_ROOT'].'/include/PHPExcel.php');
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet()->setTitle($arr_words['catalog']);
	
	// Шапка таблицы
	$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1', ' ID ')
			->setCellValue('B1', " {$arr_words['item_type']} ")
			->setCellValue('C1', " {$arr_words['brand']} ")
			->setCellValue('D1', " {$arr_words['model']} ")
			->setCellValue('E1', " {$arr_words['only_pcb']} ")
			->setCellValue('F1', " {$arr_words['cell']} ")
			->setCellValue('G1', " {$arr_words['sn']} ")
			->setCellValue('H1', " {$arr_words['pn']} ")
			->setCellValue('I1', " {$arr_words['fw']} ")
			->setCellValue('J1', " {$arr_words['quantity']} ")
			->setCellValue('K1', " {$arr_words['cost_price']} ")
			->setCellValue('L1', " {$arr_words['price']} ")
			->setCellValue('M1', " {$arr_words['for_sale']} ")
			->setCellValue('N1', " {$arr_words['additional_info']} ");
			
	$styleArray = array(
		'font' => array(
			'bold' => true,
		),
		'alignment' => array(
			'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
			'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
		)
	);

	$styleArray2 = array(
		'alignment' => array(
			'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
			'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
		)
	);

	$objPHPExcel->getDefaultStyle()->getFont()->setName('Arial');
	$objPHPExcel->getDefaultStyle()->getFont()->setSize(10);
	$objPHPExcel->getActiveSheet()->getStyle('A1:N1')->applyFromArray($styleArray);


	$objSheet = $objPHPExcel->getActiveSheet();

	$objSheet->getColumnDimension('A')->setAutoSize(true);
	$objSheet->getColumnDimension('B')->setAutoSize(true);
	$objSheet->getColumnDimension('C')->setAutoSize(true);
	$objSheet->getColumnDimension('D')->setAutoSize(true);
	$objSheet->getColumnDimension('E')->setAutoSize(true);
	$objSheet->getColumnDimension('F')->setAutoSize(true);
	$objSheet->getColumnDimension('G')->setAutoSize(true);
	$objSheet->getColumnDimension('H')->setAutoSize(true);
	$objSheet->getColumnDimension('I')->setAutoSize(true);
	$objSheet->getColumnDimension('J')->setAutoSize(true);
	$objSheet->getColumnDimension('K')->setAutoSize(true);
	$objSheet->getColumnDimension('L')->setAutoSize(true);
	$objSheet->getColumnDimension('M')->setAutoSize(true);
	$objSheet->getColumnDimension('N')->setWidth(100);

	$i = 2;
	$sql = "SELECT *,(".($config['lang'] == '' ? "SELECT `name` FROM `structure` WHERE `id`=`catalog`.`s_id` LIMIT 1" : "SELECT `name` FROM `structure_lang` WHERE `s_id`=`catalog`.`s_id` AND `lang`='{$config['lang']}' LIMIT 1").") AS `brand` FROM `catalog` WHERE `u_id`={$_SESSION['account']['id']} ORDER BY `brand` ASC, `name` ASC";
	$res = $mysqli->query($sql);
	while ($row = $res->fetch_assoc()) {
		$objSheet->setCellValue('A'.$i, $row['id'])
			->setCellValue('B'.$i, $arr_types[$row['type']])
			->setCellValue('C'.$i, $row['brand'])
			->setCellValue('D'.$i, $row['name'])
			->setCellValue('E'.$i, ($row['only_pcb'] ==  1 ? $arr_words['yes'] : $arr_words['no']))
			->setCellValue('F'.$i, $row['address'])
			->setCellValue('G'.$i, $row['sn'])
			->setCellValue('H'.$i, $row['pn'])
			->setCellValue('I'.$i, $row['fw'])
			->setCellValue('J'.$i, $row['count'])
			->setCellValue('K'.$i, $row['cost_price'])
			->setCellValue('L'.$i, $row['price'])
			->setCellValue('M'.$i, ($row['for_sale'] ==  1 ? $arr_words['yes'] : $arr_words['no']))
			->setCellValue('N'.$i, $row['info'])
			->getStyle("A{$i}:K{$i}")->applyFromArray($styleArray2);
		$i++;
	}
	$i--;
	$objSheet->getStyle("A1:A{$i}")->getFill()->applyFromArray(array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'startcolor' => array('rgb' => 'eeeeee')));

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="'.$file.'"');
	header('Cache-Control: max-age=0');

	$file = uniqid().'-'.time().'-'.rand(100000, 999999).'.tmp';
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file);
	chmod($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file,0777);
	ob_clean();
	flush();
	readfile($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file);
	unlink($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file);
	die();
}

if (isset($_SESSION['upload_success'])) {
	$smarty->assign('success', $_SESSION['upload_success']);
	unset($_SESSION['upload_success']);
}

$config['catalog']['child_name'] = $arr_words['download_edit'];

$smarty->assign('template', 'account.catalog.download.tpl');
?>