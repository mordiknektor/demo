DELIMITER $$
--
-- Функция получения стоимости минуты звонка по номеру
--
CREATE FUNCTION `get_rate`(s VARCHAR(64)) RETURNS char(6) CHARSET utf8
BEGIN
	-- Объявляем переменные
	DECLARE rate CHAR(6) DEFAULT '';
	DECLARE leng INT DEFAULT 0;
	DECLARE flag INT DEFAULT 1;
	-- Курсор чтения таблицы 
	DECLARE curl CURSOR FOR SELECT Price FROM rates WHERE Code_Prefix=s LIMIT 1;

	-- Если номер начинается с +, отбрасываем его
	IF LEFT(s,1) = '+' THEN 
		SET s = SUBSTRING(s FROM 2); 
	END IF;
	-- Если номер начинается с 00, отбрасываем 2 первых символа
	IF LEFT(s,2) = '00' THEN 
		SET s = SUBSTRING(s FROM 3);
	END IF;
	
	-- Цикл поиска номера в таблице
	WHILE flag = 1 DO
		OPEN curl;
		FETCH curl INTO rate; 
		CLOSE curl;
		-- Если номер найден, заканчиваем цикл
		IF rate!='' THEN SET flag = 0;
		ELSE 
			-- Если номер не найден, проверяем длину номера, если < 2 - стоимость не найдена. Иначе отбрасываем последнюю цифру номера
			SET leng = LENGTH(s);
			IF leng < 2 THEN 
				SET flag = 0; 
				SET rate = '-1';
			ELSE SET s = SUBSTRING(s,1,leng-1);
			END IF;
		END IF;
	END WHILE;
	RETURN rate;
END$$