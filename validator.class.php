<?php
/**
 * Класс, обеспечивающий проверку элементов массива
 */

class Validator {

    /**
     * Параметры по умолчанию для проверки значений
     *
     * $settings["rules"][<rule_name>] = array(<regexpr>, <err_text>)
     * $settings["keys"][<key_name>] = array(<table_name>, <column_name>, <err_text>[, "array_keys"])
     * "array_keys" - значения для проверки получать из ключей массива
     *
     * @var array
     */
    public static $settings = array(
        "rules" => array(
            'required'  => array('/\w+/', 'Это поле необходимо заполнить.'),
            'digits'    => array('/^\d+$/', 'Пожалуйста, вводите только цифры.'),
            'number'    => array('/^[0-9]+(?:\.[0-9]*)?$/', 'Пожалуйста, введите число.')
        ),
        "keys" => array(
            "district_id"       => array("districts", "district_id", "Такого округа не существует"),
            "area_id"           => array("areas", "area_id",  "Такого участка не существует"),
            "party_id"          => array("parties", "party_id", "Такой партии не существует"),
            "majoritarian_id"   => array("majoritarians", "majoritarian_id", "Такого мажоритарного кандидата не существует"),
            "parties"           => array("parties", "party_id", "Такой партии не существует", "array_keys"),
            "majoritarians"     => array("majoritarians", "majoritarian_id", "Такого мажоритарного кандидата не существует", "array_keys"),
        ),

    );

    /**
     * Преобразует многомерный массив в массив <путь> = <значение>
     *
     * @param array  $arr               исходный многомерный массив
     * @param string $left_divider      левый разделитель для каждого элемента пути
     * @param string $right_divider     правый разделитель для каждого элемента пути
     * @param string $path              переменная используемая для рекурсивного построения пути
     *
     * @return array
     */
    public static function arrayToPath($arr, $left_divider = "[", $right_divider = "]",  $path = ""){
        $result = array();
        foreach ($arr as $item_key => $item) {
            if (is_array($item)) {
                if (!empty($path)) {
                    $result = array_merge($result, self::arrayToPath($item, $left_divider, $right_divider,  $path.$left_divider.$item_key.$right_divider));
                } else {
                    $result = array_merge($result, self::arrayToPath($item, $left_divider, $right_divider, $item_key));
                }
            } else {
                if ($path) {
                    $result[$path.$left_divider.$item_key.$right_divider] = $item;
                } else {
                    $result[$item_key] = $item;
                }
            }
        }
        return $result;
    } // end of function arrayToPath()


    /**
     * Проверка всех значений массива на ошибки по указанным правилам
     *
     * Примеры:
     * <code>
     * $data = array(
     *      "id" => "qwerty",
     *      "goods_prices" => array(
     *          "phone" => 1000,
     *          "tv"    => 10000,
     *          "pc"    => ""
     *      ),
     * );
     *
     * $rules = array(
     *      "id" => array("required", "digits"),
     *      // проверка полей имеющих вид goods_prices[<имя товара>] = <значение>
     *      "/^goods_prices\[.+?\]$/" => array("required", "number"),
     *      "city" => array("required", "msg" => "Введите название города")
     * );
     *
     * $result = validate($data, $rules);
     * </code>
     * вернет следующее:
     * <code>
     * array(
     *      "id" => "Пожалуйста, вводите только цифры.",
     *      "goods_prices[pc]"  => "Это поле необходимо заполнить."
     *      "city"  => "Введите название города"
     * );
     * </code>
     *
     *
     * @param $data     масссив который нужно проверять
     * @param $rules    правила, настройки для правил указаны в {@link $settings["rules"]},
     *                  так же можно дополнить правила более сложными в блоке CUSTOM RULES
     * @param $keys     если после проверки массива по указанным правилам ошибок нет, тогда:
     *                   - true - будет проверка ключей, настройки для которых заранее заданы в {@link $settings["keys"]}
     *                   - array - будет проверка ключей, настройки для которых берутся из этой переменной
     *
     * @return array
     */
    public static function validate($data, $rules, $keys = null) {
        $result = array();
        $parsed_data = self::arrayToPath($data);

        // go through rules
        foreach($rules as $rule_name => $rule) {
            $matched_fields = null;
            // search matched fields
            foreach( $parsed_data as $field_name => $field_val) {
                // search matched field name

                // if rule name is regexp
                if (substr($rule_name,0,1) == "/") {
                    if (preg_match($rule_name, $field_name)) {
                        $matched_fields[$field_name] = $field_val;
                    }
                } else {
                    if ($rule_name == $field_name) {
                        $matched_fields[$field_name] = $field_val;
                    }
                }
            }


            // if matched fields not found
            if (empty($matched_fields)) {
                // if matched fields are required, generate error
                if (array_search("required", $rule)!==false) {
                    $result[$rule_name] = self::$settings["rules"]["required"][1];
                }
            } else {
                // go through matched fields
                foreach($matched_fields as $field_name => $field_val) {
                    // go through rule settings
                    foreach($rule as $rule_setting_name => $rule_setting) {
                        if ($rule_setting_name === "msg") {
                            continue;
                        }

                        // if field is empty and not required, skip it
                        if (array_search("required", $rule)===false && empty($field_val)) {
                            break;
                        }

                        // if rule defined in $settings["rules"]
                        if ( is_numeric($rule_setting_name) && !empty(self::$settings["rules"])) {
                            if (!preg_match(self::$settings["rules"][$rule_setting][0], $field_val)) {
                                if (!empty($rule["msg"])) {
                                    $result[$field_name] = $rule["msg"];
                                } else {
                                    $result[$field_name] = self::$settings["rules"][$rule_setting][1];
                                }
                                break;
                            }
                        } else {
                            // Here you can write your own rule
                            // use $field_val, $rule_setting_name, $rule_setting

                            // CUSTOM RULES

                            switch ($rule_setting_name) {
                                case  "range":
                                    if (!($rule_setting[0]<=$field_val && $field_val<= $rule_setting[1])) {
                                        $result[$field_name] = "Пожалуйста, введите число от {$rule_setting[0]} до {$rule_setting[1]}.";
                                        break;
                                    }
                                    break;
                            }

                            // end of CUSTOM RULES
                        }

                    } // foreach($rule as $rule_setting_name => $rule_setting)
                } //  foreach($matched_fields as $field_name => $field)
            } //  if (empty($matched_fields))
        }

        if (empty($result)) {
            if ($keys === true) {
                $result = self::validateKeys($data);
            } elseif (is_array($keys)) {
                $result = self::validateKeys($data, $keys);
            }
        }

       return $result;
    } // end of validate


    /**
     * Проверка ключей на их существование в базе
     *
     * @param      $data                массив <ключ> = <значение>
     * @param null $keys_settings       параметры проверки ключа
     * @param bool $get_first_error     останавливается на первой ошибке и возвращает ее
     *
     * @return array                    массив ошибок <имя ключа> = <текст ошибки>
     */
    public static function validateKeys($data, $keys_settings = null, $get_first_error = false) {
        if (empty($keys_settings)) {
            $keys_settings = self::$settings["keys"];
        }

        $validate_errors = array();
        foreach ($keys_settings as $key_name => $key_setting) {
            if (!empty($data[$key_name])) {
                if (array_search("array_keys", $key_setting)) {
                    $validate_errors[$key_name] = self::validateKey($key_setting[0], $key_setting[1], array_keys($data[$key_name]), $key_setting[2]);
                } else {
                    $validate_errors[$key_name] = self::validateKey($key_setting[0], $key_setting[1], $data[$key_name], $key_setting[2]);
                }


                if ($get_first_error && !empty($validate_errors[$key_name])) {
                    break;
                }
            }

        }
        // если массивы - пусты, удаляем их
        foreach ($validate_errors as $key => $val) {
            if (empty($val)) {
                unset($validate_errors[$key]);
            }
        }

        return $validate_errors;
    } // end of validateKeys()


    /**
     * Проверка ключа на его существование в БД
     *
     * @param $table_name   имя таблицы
     * @param $key_name     имя ключа
     * @param $keys         одно или несколько значений ключа
     * @param $err_text     текст ошибки
     *
     * @return array
     */
    public static function validateKey($table_name, $key_name, $keys, $err_text) {
       $validate_errors = array();
       $is_array = is_array($keys);
       if (!$is_array) {
           $keys = array($keys => $keys);
       }
       if ($diff = array_diff($keys, Db::select($key_name)->from($table_name)->fetchCol($key_name." IN(?@)", $keys)) ) {
            if ($is_array) {
                foreach ($diff as $item) {
                    $validate_errors[$item] = $err_text;
                }
            } else {
                $validate_errors = $err_text;
            }
       }

       return $validate_errors;
    } // end of validateKey()


} // end of class Validator