// Изменение видимости чекбоксов магазинов (class="l-shops") при кликах на чекбоксы их райнов (input class="districts-filter")

// Проверка пересечения массивов
function my_check_arr(arr1, arr2) {
    let len1 = arr1.length;
    let len2 = arr2.length;
    let i,j;
	for (i = 0; i < len1; i++) {
		for (j = 0; j < len2; j++) {
			if (arr1[i] == arr2[j]) {
				return true;
			}
		}
    }
    return false;
}

// Вешаем событие на изменение чекбокса
$('input.districts-filter').change( function() {
	let str = '';
	let arr_checked = [];
	let i = 0;
	// Получаем массив отмеченных районов
	$('input.districts-filter').each( function() {
		if ($(this).is(':checked')) {
			str += $(this).attr('value')+'.';
			arr_checked[i++] = $(this).attr('value');
		}
	});
	if (str != '') {
		// Проходим по магазинам
		$('.l-shops').each( function() {
			if ($(this).data('district') !='') {
				// Проверяем, есть ли такой район в магазине
				let arr_tmp = $(this).data('district').split(',');
				if (my_check_arr(arr_checked, arr_tmp)) {
					$(this).children().prop('checked', true);
					$(this).css('display', 'inline-block');
				} else {
					$(this).hide();
				}
			} else {
				$(this).hide();
			}
		});
	} else {
		$('input.shops-filter').prop('checked', true);
		$('.l-shops').css('display', 'inline-block');
	}
});