<?
// Отдача конента страницы
if ($config['arr_url'][0]=='img') {
	if (substr($config['arr_url'][1],0,7) == 'number.') {
		//Подключаем капчу
		require($_SERVER['DOCUMENT_ROOT'].'/modules/number/number.php');
	} else {
		//Изображения
		require($_SERVER['DOCUMENT_ROOT'].'/modules/images/images.php');
	}
} elseif ($config['arr_url'][0]=='rss') {
	// Новости rss
	require($_SERVER['DOCUMENT_ROOT'].'/modules/main/news/news.rss.php');	
} elseif ($config['arr_url'][0]=='cms') {
	if ($_SERVER['HTTPS'] == 'on') {
		$count = 8;
	} else {
		$count = 7;
	}
	if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !='' && strpos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST']) != $count) {
		// Простейшая защита от CSRF
		header('Location: /');
		die();
	}
	$smarty = new Smarty;
	$smarty->setTemplateDir($_SERVER['DOCUMENT_ROOT'].'/templates/cms')->setCompileDir($_SERVER['DOCUMENT_ROOT'].'/templates/cms/_cache');
	$_SESSION['rand'] = rand(0,100);
	//Выходим из cms
	if (isset($config['arr_url'][1]) && $config['arr_url'][1]=='exit' && isset($_SESSION['login'])) {
		$_SESSION = array();
		header('Location: /cms/');
		die();
	}
	//Загружаем cms
	if (isset($_SESSION['login'])) {
		// Проверка сессии
		$sql = "SELECT `role`,`arr_modules` FROM `users` WHERE `id`={$_SESSION['login']} AND `visible`=1 LIMIT 1";
		$res = $mysqli->query($sql);
		if (list($tmp, $arr_modules) = $res->fetch_row()) {
			$_SESSION['role'] = $tmp;
		} else {
			$_SESSION = array();
			header('Location: /cms/');
			die();
		}
		//Модули cms
		$title = my_title().' '.$config['structure']['title_separator'].' '.$config['structure']['default_cms_title'];
		$smarty->assign('title',$title);
		if ($_SESSION['role'] != 0) {
			// Получаем права доступа пользователя
			$sql = "SELECT `a`.`s_id`,`b`.`module` FROM `users_rights` `a`, `structure` `b` WHERE `a`.`u_id`={$_SESSION['login']} AND `a`.`s_id`=`b`.`id`";
			$res = $mysqli->query($sql);
			$arr_rights = array();
			$arr_used_modules = array('structure'=>true,'change'=>true,'exit'=>true);
			while(list($s_id,$module) = $res->fetch_row()) {
				$arr_rights[$s_id] = true;
				if ($module != '') {
					$arr_used_modules[$module] = true;
				}
			}
			if ($arr_modules != '') {
				// Глобально включенные модули
				$arr_tmp = explode(',', trim($arr_modules,','));
				foreach ($arr_tmp as $v) {
					$arr_used_modules[$v] = true;
				}		
			}
		}
		//Контент из cms
		$config['module'] = $config['cms'][$config['url']]['module'];
		if (is_file($_SERVER['DOCUMENT_ROOT'].'/modules/cms/'.$config['module'].'/'.$config['module'].'.php') && ($_SESSION['role']==0 || isset($arr_used_modules[$config['module']]))) {
			if (isset($config['modules'][$config['module']]['multi'])) {
				require ($_SERVER['DOCUMENT_ROOT'].'/modules/cms/structure/structure.module.php');
			} else {
				require ($_SERVER['DOCUMENT_ROOT'].'/modules/cms/'.$config['module'].'/'.$config['module'].'.php');
			}
		} else {
			$smarty->assign('arr_taxon', array('Система администрирования сайтом'));
			$smarty->assign('content', '<p>К сожалению, запрашиваемая Вами страница не найдена.</p>');
			$smarty->assign('template','notice.tpl');
		}
		//Меню
		$menu = array();
		$menu[] = array('title'=>'Перейти на сайт', 'url'=> '/', 'selected'=>0,'blank'=>true);
		$menu[] = array('title'=>'Перейти на страницу', 'url'=> isset($page_url) ? $page_url : '/', 'selected'=>0,'hr'=>true,'blank'=>true);
		foreach($config['cms'] as $key=>$v) {
			if ($_SESSION['role']==0 || isset($arr_used_modules[$v['module']])) $menu[] = array('title'=>$v['title'], 'url'=> '/cms/'.($key=='' ? '' : $key.'/'), 'selected'=>($config['arr_url'][1]==$key ? 1 : 0),'hr'=>isset($v['hr']));
		}
		$smarty->assign('arr_menu',$menu);
	} else {
		//Авторизация
		require($_SERVER['DOCUMENT_ROOT'].'/modules/cms/login/login.php');
	}
	mysqli_close($mysqli);
	$smarty->display('index.tpl');
} else {
	$_SESSION['rand'] = rand(0,100);
	$module = '';
	//Загружаем сайт
	if ($config['type']==0) {
		//Категория
		if ($config['link']!='') {
			// Перенаправление по ссылке
			header('Location: '.$config['link']);
			die();
		} else {
			$link = my_category($config['id']);
			if ($link) {
				header('Location: /'.$config['url_all'].'/'.$link);
				die();
			}
		}
	}
	$smarty = new Smarty;
	$smarty->setTemplateDir($_SERVER['DOCUMENT_ROOT'].'/templates/main')->setCompileDir($_SERVER['DOCUMENT_ROOT'].'/templates/main/_cache');
	
	if (isset($_SESSION['login'])) {
		// Ссылка на редактирование страницы
		$smarty->assign('edit_url','/cms/'.$config['id'].'/1/edit.html');
	}
	if ($config['type'] == 0) {
		//Категория
		$smarty->assign('content','<p>'.$config['structure']['no_content'].'</p>');
	} else {
		if ($config['type'] == 1) {
			//Страница
			if ($config['content'] != '') {
				if (strpos($config['content'], '{subsections_list}') !== false) {
					// Список подкатегорий по тегу {subsections_list}
					$sql = "SELECT IF(`subsections_name`!='',`subsections_name`,`name`) AS `name`,`image`,IF(`link`!='', `link`, `url_all`) AS `url` FROM `structure` WHERE `p_id`={$config['id']} AND `visible`=1 AND `name`!='' ORDER BY `sort` ASC";
					$res = $mysqli->query($sql);
					$arr_items = array();
					while ($row = $res->fetch_assoc()) {
						$arr_items[] = $row;
					}
					if ($arr_items) {
						$smarty->assign('arr_subcategory',$arr_items);
						$subsections = $smarty->fetch('subcategory.tpl');
					} else {
						$subsections = '';
					}
					$config['content'] = str_replace('{subsections_list}', $subsections, $config['content']);
				}			
				$smarty->assign('content',$config['content']);
			} else {
				$smarty->assign('content','<p>'.$config['structure']['no_content'].'</p>');
			}
		}	
		if ($config['module'] != '') {
			//Модуль
			if (file_exists($_SERVER['DOCUMENT_ROOT'].'/modules/main/'.$config['module'].'/'.$config['module'].'.php')) {
				if ((require $_SERVER['DOCUMENT_ROOT'].'/modules/main/'.$config['module'].'/'.$config['module'].'.php') == 404) {
					// Страница не найдена
					$_SESSION['sitemap_error'] = 1;
					header('HTTP/1.1 404 Not found');
					$config['module'] = 'sitemap';
					require($_SERVER['DOCUMENT_ROOT'].'/modules/main/sitemap/sitemap.php');
					$config['arr_title'] = array($config['modules']['sitemap']['title']);
					$config['arr_name'] = array($config['modules']['sitemap']['title']);
					unset($config['catalog']);
					$smarty->assign('arr_taxon',my_taxon());
					$smarty->assign('title',my_title().$config['structure']['default_title']);
				};
			} elseif ($config['type'] == 2) {
				$smarty->assign('content',$config['structure']['no_content']);
			}
		}
	}
	if ($config['keywords'] == '') {
		$config['keywords'] = $config['structure']['default_keywords'];
	}
	if ($config['description'] == '') {
		$config['description'] = $config['structure']['default_description'];
	}
	
	// Дополнительный контет страницы
	foreach($config['modules'] as $m=>$v) {
		if (isset($v['allpages']) && (!isset($v['onlyhome']) || $config['url']=='')) {
			require($_SERVER['DOCUMENT_ROOT'].'/modules/main/'.$m.'/'.$m.'.allpages.php');
		}
	}
	
	// меню сверху
	if (is_file($_SERVER['DOCUMENT_ROOT'].'/xcache/structure/menu_top.cache')) {
		$smarty->assign('arr_menu_top',unserialize(file_get_contents($_SERVER['DOCUMENT_ROOT'].'/xcache/structure/menu_top.cache')));
	} else {
		$smarty->assign('arr_menu_top',my_flat_menu(0,'',1));
	}
	// меню снизу
	if (is_file($_SERVER['DOCUMENT_ROOT'].'/xcache/structure/menu_bottom.cache')) {
		$smarty->assign('arr_menu_bottom',unserialize(file_get_contents($_SERVER['DOCUMENT_ROOT'].'/xcache/structure/menu_bottom.cache')));
	} else {
		$smarty->assign('arr_menu_bottom',my_flat_menu(0,'',2));
	}
	// меню слева
	if (is_file($_SERVER['DOCUMENT_ROOT'].'/xcache/structure/menu_left.cache')) {
		$smarty->assign('arr_menu_left',unserialize(file_get_contents($_SERVER['DOCUMENT_ROOT'].'/xcache/structure/menu_left.cache')));
	} else {
		$smarty->assign('arr_menu_left',my_menu(0,'',3));
	}
	
	if ($config['structure']['only_page_title']==1) {
		//Только заголовки страниц
		if (isset($config['catalog']) && isset($config['catalog']['child_name'])) $title = ($config['catalog']['child_title']==''?$config['catalog']['child_name']:$config['catalog']['child_title']);
		elseif (isset($config['catalog']) && isset($config['catalog']['parent_name'])) $title = ($config['catalog']['parent_title']==''?$config['catalog']['parent_name']:$config['catalog']['parent_title']);
		else $title = $config['arr_title'][count($config['arr_title'])-1];
	} else {
		$title = my_title().$config['structure']['default_title'];
	} 
	
	$arr_taxon = my_taxon();
	if ($arr_taxon) {
		$count = count($arr_taxon);
		$smarty->assign('taxon_last',$arr_taxon[$count-1]['title']);
		unset($arr_taxon[$count-1]);
		$smarty->assign('arr_taxon',$arr_taxon);
	}
	$smarty->assign('noindex',$config['noindex']);	
	$smarty->assign('no_content',$config['structure']['no_content']);	
	$smarty->assign('title',$title);
	$smarty->assign('meta',$config['meta']);
	$smarty->assign('keywords',$config['keywords']);
	$smarty->assign('description',$config['description']);
	$smarty->assign('default_email',my_settings('feedback','email'));
	$smarty->assign('id',$config['id']);	
	$smarty->assign('arr_id',$config['arr_id']);	
	$smarty->assign('use_share',$config['use_share']);
	$smarty->assign('uri','/'.($config['url_all']!=''?$config['url_all'].'/':''));
	$smarty->assign('domain',($config['structure']['primary_domain'] != '' ? $config['structure']['primary_domain'] : $_SERVER['SERVER_NAME']));
	$smarty->assign('module',$config['module']);	
	$smarty->assign('page_pred',$config['page_pred']);	
	if (!isset($config['catalog']) && isset($config['textblock'])) {
		$smarty->assign('textblock',$config['textblock']);	
	}
	
	mysqli_close($mysqli);
	$smarty->display('index.tpl');
}
?>