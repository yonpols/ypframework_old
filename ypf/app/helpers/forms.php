<?php
    $form_ids = 0;

    function form($url = '', $method = 'POST', $options = array())
    {
        global $form_ids;

        $html = sprintf('<form id="form%d" action="%s" method="%s"',
            ++$form_ids,
            $url,
            htmlentities($method, ENT_QUOTES, 'utf-8'));

        foreach ($options as $key=>$val)
            $html .= sprintf(' %s="%s"', $key, $val);

        return $html.'>';
    }

    function form_end()
    {
        return '</form>';
    }

    function form_textfield($name, $object = '', $attrs = array())
    {
        return _form_inputfield('text', $name, $object, $attrs);
    }

    function form_passwordfield($name, $object = '', $attrs = array())
    {
        return _form_inputfield('password', $name, $object, $attrs);
    }

    function form_submit($name, $object = '', $attrs = array())
    {
        return _form_inputfield('submit', $name, $object, $attrs);
    }

    function form_button($name, $text, $jsFunc, $attrs = array())
    {
        $id = _form_fieldname($name);

        $html = sprintf('<button id="%s" name="%s" onclick="%s"',
            $id['id'], $id['name'], htmlentities($jsFunc, ENT_QUOTES, 'utf-8'));

        foreach ($attrs as $k=>$v)
            $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));

        $html .= '>'.htmlentities($text, ENT_QUOTES, 'utf-8').'</button>';

        return $html;
    }

    function form_hiddenfield($name, $object = '', $attrs = array())
    {
        return _form_inputfield('hidden', $name, $object, $attrs);
    }

    function form_filefield($name, $object = '', $attrs = array())
    {
        return _form_inputfield('file', $name, $object, $attrs);
    }

    function form_radiofield($name, $value, $object = '', $attrs = array())
    {
        if (is_object($object) && isset ($object->{$name}) && ($object->{$name} == $value))
            $attrs['checked'] = 'checked';

        return _form_inputfield('radio', $name, $object, $attrs, $value);
    }

    function form_checkfield($name, $value, $object = '', $attrs = array())
    {
        if (is_object($object) && isset ($object->{$name}) && ($object->{$name} == $value))
            $attrs['checked'] = 'checked';

        return _form_inputfield('checkbox', $name, $object, $attrs, $value);
    }

    function form_select($name, $values, $object = '', $allowBlank=false, $attrs = array())
    {
        $value = null;
        $id = _form_fieldname($name, $object, $value);

        $html = sprintf('<select id="%s" name="%s"',
            $id['id'], $id['name']);

        if (is_array($attrs))
        foreach ($attrs as $k=>$v)
            $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));

        $html .= '>';

        if ($allowBlank)
        {
            $html .= '<option value=""';
            if ($value === null) $html .= ' selected="selected"';
            $html .= '></option>';
        }

        foreach ($values as $k=>$v)
        {
            if (is_object($v) && ($v instanceof Model))
            {
                $key = $v->getSerializedKey();

                $html .= sprintf('<option value="%s"', htmlentities($key, ENT_QUOTES, 'utf-8'));
                if ($key == $value) $html .= ' selected="selected"';
            } else {
                $html .= sprintf('<option value="%s"', htmlentities($k, ENT_QUOTES, 'utf-8'));
                if ($k == $value) $html .= ' selected="selected"';
            }
            $html .= sprintf('>%s</option>', htmlentities($v, ENT_QUOTES, 'utf-8'));
        }

        $html .= '</select>';

        if (is_object($object) && ($object instanceof Model) && $object->getError($name))
            $html .= sprintf('<span class="error"><ul><li>%s</li></ul></span>',
                implode('</li><li>', $object->getError($name)));

        return $html;
    }

    function form_textarea($name, $object = '', $attrs = array())
    {
        $value = null;
        $id = _form_fieldname($name, $object, $value);

        $html = sprintf('<textarea id="%s" name="%s"',
            $id['id'], $id['name']);

        foreach ($attrs as $k=>$v)
            $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));

        $html .= '>'.htmlentities($value, ENT_QUOTES, 'utf-8');

        $html .= '</textarea>';

        if (is_object($object) && ($object instanceof Model) && $object->getError($name))
            $html .= sprintf('<span class="error"><ul><li>%s</li></ul></span>',
                implode('</li><li>', $object->getError($name)));

        return $html;
    }

    function form_relationfield($name, $object, $condition=array(), $allowBlank=false)
    {
        $relation = $object->getRelationObject($name);
        if (!$relation instanceof ModelBaseRelation)
            return;

        $dao = eval(sprintf('return new %s();', $relation->getRelatedModelName()));
        $values = $dao->select($condition)->toArray();

        return form_select($name, $values, $object, $allowBlank);
    }

    function form_datefield($name, $object)
    {
        return form_textfield($name, $object, array('class'=>'form_field date'));
    }

    function _form_inputfield($type, $name, $object = '', $attrs = array(), $value = null)
    {
        $id = _form_fieldname($name, $object, $value);

        if (is_object($object) && ($object instanceof Model) && $object->getError($name))
        {
            $errors = sprintf('<span class="error"><ul><li>%s</li></ul></span>',
                implode('</li><li>', $object->getError($name)));
            $attrs['class'] = (isset($attrs['class'])? $attrs['class']: '').' error';
        } else
            $errors = '';

        $html = sprintf('<input id="%s" type="%s" name="%s" value="%s"',
            $id['id'],
            $type,
            $id['name'],
            htmlentities($value, ENT_QUOTES, 'utf-8'));

        if (is_array($attrs))
            foreach ($attrs as $k=>$v)
                $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));
        $html .= '/>';

        return $html.$errors;
    }

    function _form_fieldname($name, $object = null, &$value = null)
    {
        if (is_object($object))
        {
            if (isset ($object->{$name}))
            {
                if ($value === null) $value = $object->{$name};
                if ($value instanceof Model)
                    $value = $value->getSerializedKey();
            }

            $id = sprintf('%s_%s', get_class($object), $name);
            $name = sprintf('%s[%s]', get_class($object), $name);
        } else
        {
            if ($value === null) $value = $object;
            $id = $name;
        }

        return array('id' => $id, 'name' => $name);
    }

    function form_process_uploaded_file($model, $field, $path)
    {
        $modelName = get_class($model);

        if (!isset($_FILES[$modelName]))
            return false;

        $data = array(
            'type' => $_FILES[$modelName]['type'][$field],
            'tmp_name' => $_FILES[$modelName]['tmp_name'][$field],
            'name' => $_FILES[$modelName]['name'][$field],
            'error' => $_FILES[$modelName]['error'][$field],
            'size' => $_FILES[$modelName]['size'][$field],
        );

        if (is_uploaded_file($data['tmp_name']))
        {
            $search = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u");
            $replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
            $file_name = str_replace($search, $replace, $data['name']);
            $extension = substr($file_name, strrpos($file_name, '.'));

            $file_name = substr($file_name, 0, -strlen($extension));
            $file_name = preg_replace('/[^\w\-~_\.]+/u', '-', $file_name);

            $i = '';
            while (file_exists($path.$file_name.$i.$extension))
                $i += 1;

            move_uploaded_file($data['tmp_name'], $path.$file_name.$i.$extension);

            if (file_exists($path.$model->{$field}))
                @unlink($path.$model->{$field});

            $model->{$field} = $file_name.$i.$extension;
            $model->{$field.'_tamanio'} = filesize($path.$file_name.$i.$extension);
            return true;
        }

        return false;
    }
?>
