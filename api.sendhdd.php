<?
// Модуль API
// Добавление товара

$input = file_get_contents('php://input');
if ($input != '') { 
	$data = json_decode($input, true);
	if ($data) {
		$error = 0;
		if (isset($data['token']) && strlen($data['token']) == 40) {
			$ip = $mysqli->real_escape_string($_SERVER['REMOTE_ADDR']);
			$sql = "SELECT `ip` FROM `account_ip` WHERE `ip`='{$ip}' LIMIT 10";
			$res = $mysqli->query($sql);
			$tmp = $res->num_rows;
			if ($tmp == 10) {
				$error = 5;
			} else {
				// Проверяем токен
				$token = $mysqli->real_escape_string($data['token']);
				$sql = "SELECT `id` FROM `account` WHERE `token`='{$token}' AND `visible`=1 LIMIT 1";
				$res = $mysqli->query($sql);
				if (($mysqli->error == '') && (list($u_id) = $res->fetch_row())) {
					$catalog = array();
					$catalog['name'] = (isset($data['model']) ? trim(mb_substr($data['model'],0,128)) : '');

					$catalog['brand'] = $mysqli->real_escape_string(trim(mb_substr($data['brand'],0,64)));
					$sql = "SELECT `id` FROM `structure` WHERE `module`='catalog' AND `name`='{$catalog['brand']}' LIMIT 1";
					$res = $mysqli->query($sql);
					if (list($tmp) = $res->fetch_row()) {
						$s_id = $tmp;
					} else {
						$catalog['brand'] = '';
					}
					
					if ($catalog['name'] != '' && $catalog['brand'] != '') {
						$catalog['image'] = '';
						if ($data['photo'] != '') {
							// Декодируем изображение
							if (($tmp = base64_decode($data['photo'], true)) !== false) {
								// Сохраняем на диск
								$file = uniqid().'-'.time().'-'.rand(1000,9999);
								if (file_put_contents($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file, $tmp)) {
									chmod($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file, 0777);
									$size = filesize($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file);
									if ($size >= 8 && $size <= 1048576) {
										if (($fp = getimagesize($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file)) !== false) {
											$ext = '';
											if ($fp['mime'] == 'image/png') {
												$ext = 'png';
											} elseif ($fp['mime'] == 'image/jpeg' || $fp['mime'] == 'image/jpg') {
												$ext = 'jpg';
											}
											if ($ext != '') {
												$fp['filename'] = time();
												$image = $fp['filename'].'.'.$ext;
												$i = 1;
												$dir = my_get_dir($image);
												if (!is_dir($_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.rtrim($dir,'/'))) {
													mkdir($_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.rtrim($dir,'/'), 0777);				
												}												
												while (is_file($_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.$dir.$image)) {
													$image = $fp['filename'].'-'.($i++).'.'.$ext;			
												}
												copy($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file, $_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.$dir.$image);
												chmod($_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.$dir.$image,0777);
												$catalog['image'] = $image;
											} else {
												$error = 4;
											}
										} else {
											$error = 4;
										}									
									} else {
										$error = 4;
									}
									unlink($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file);
								} else {
									$error = 4;
								}
							} else {
								$error = 4;
							}
						}
						if ($error == 0) {
							$catalog['image_2'] = '';
							if ($data['photo_2'] != '') {
								// Декодируем изображение
								if (($tmp = base64_decode($data['photo_2'], true)) !== false) {
									// Сохраняем на диск
									$file = uniqid().'-'.(time()+1).'-'.rand(1000,9999);
									if (file_put_contents($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file, $tmp)) {
										chmod($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file, 0777);
										$size = filesize($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file);
										if ($size >= 8 && $size <= 1048576) {
											if (($fp = getimagesize($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file)) !== false) {
												$ext = '';
												if ($fp['mime'] == 'image/png') {
													$ext = 'png';
												} elseif ($fp['mime'] == 'image/jpeg' || $fp['mime'] == 'image/jpg') {
													$ext = 'jpg';
												}
												if ($ext != '') {
													$fp['filename'] = time()+1;
													$image = $fp['filename'].'.'.$ext;
													$i = 1;
													$dir = my_get_dir($image);
													if (!is_dir($_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.rtrim($dir,'/'))) {
														mkdir($_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.rtrim($dir,'/'), 0777);				
													}												
													while (is_file($_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.$dir.$image)) {
														$image = $fp['filename'].'-'.($i++).'.'.$ext;			
													}
													copy($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file, $_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.$dir.$image);
													chmod($_SERVER['DOCUMENT_ROOT'].'/images/catalog/'.$dir.$image,0777);
													$catalog['image_2'] = $image;
												} else {
													$error = 4;
												}
											} else {
												$error = 4;
											}									
										} else {
											$error = 4;
										}
										unlink($_SERVER['DOCUMENT_ROOT'].'/xcache/'.$file);
									} else {
										$error = 4;
									}
								} else {
									$error = 4;
								}
							}
						}						
						if ($error == 0) {
							// Обновляем время токена
							$date = date('Y-m-d H:i:s');
							$sql = "UPDATE `account` SET `token_date`='{$date}' WHERE `id`={$u_id} LIMIT 1";
							$mysqli->query($sql);
							
							$catalog['sn'] = trim(mb_substr($data['sn'],0,64));
							if ($catalog['sn'] != '') {
								// Проверяем уникальность серийника
								$sn_esc = $mysqli->real_escape_string($catalog['sn']);
								$sql = "SELECT `id` FROM `catalog` WHERE `u_id`={$u_id} AND `sn`='{$sn_esc}' LIMIT 1";
								$res = $mysqli->query($sql);
								if ($res->num_rows == 1) {
									$error = 7;
								}		
							}
							if ($error == 0) {
								// Добавляем товар					
								$arr_types = array(0=>true, 1=>true);
								if (isset($data['type'])) {
									$catalog['type'] = (int)$data['type'];
									if (!isset($arr_types[$catalog['type']])) {
										$catalog['type'] = 0;
									}
								} else {
									$catalog['type'] = 0;
								}
								
								$catalog['pn'] = trim(mb_substr($data['pn'],0,64));
								$catalog['fw'] = trim(mb_substr($data['fw'],0,64));
								if ($catalog['type'] == 1) {
									$catalog['pcb'] = "'".$mysqli->real_escape_string(trim(mb_substr($data['pcb'],0,64)))."'";
									$catalog['pwb'] = "'".$mysqli->real_escape_string(trim(mb_substr($data['pwb'],0,64)))."'";
								} else {
									$catalog['pcb'] = 'NULL';
									$catalog['pwb'] = 'NULL';
								}						
								$catalog['address'] = trim(mb_substr($data['address'],0,64));
								$catalog['count'] = 1;
								$catalog['price'] = 0.0;
								$catalog['info'] = trim(substr(strip_tags($data['comment'], '<br><b><strong><i><ul><ol><li><table><tr><td><th><tbody>'), 0, 5000));
								$catalog['visible'] = 0;

								$sql = "SELECT `country` FROM `account` WHERE `id`={$u_id} LIMIT 1";
								$res = $mysqli->query($sql);
								list($country) = $res->fetch_row();
					
								$sql = "INSERT INTO `catalog` SET `image`='{$catalog['image']}',`image_2`='{$catalog['image_2']}',`pcb`={$catalog['pcb']},`pwb`={$catalog['pwb']},`date`=NOW(),`country`='{$country}',`u_id`={$u_id},`type`={$catalog['type']},`s_id`={$s_id},`name`='".$mysqli->real_escape_string($catalog['name'])."',`sn`='".$mysqli->real_escape_string($catalog['sn'])."',`pn`='".$mysqli->real_escape_string($catalog['pn'])."',`address`='".$mysqli->real_escape_string($catalog['address'])."',`fw`='".$mysqli->real_escape_string($catalog['fw'])."',`count`={$catalog['count']},`price`={$catalog['price']},`info`='".$mysqli->real_escape_string($catalog['info'])."',`visible`={$catalog['visible']}";
								$mysqli->query($sql);
				
								if ($mysqli->error != '') {
									$error = 6;						
								}
							}
						}
					} else {
						if ($catalog['name'] == '') {
							$error = 2;	
						} else {
							$error = 3;
						}
					}
				} else {
					$sql = "INSERT INTO `account_ip` SET `ip`='{$ip}'";
					$mysqli->query($sql);					
					$error = 1;
				}
			}
		} else {
			$error = 1;
		}
		header('Content-Type: application/json');
		die(json_encode(array('error'=>$error)));
	} else {
		return 404;
	}
} else {
	return 404;
}
?>