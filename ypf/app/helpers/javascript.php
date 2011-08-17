<?php
    function js_on_action_load($controller, $action)
    {
        if ($controller != null)
        {
            return
                sprintf('if (typeof app.%s !== "undefined") if (typeof app.%s.%s !== "undefined") eval("app.%s.%s();");',
                    class_to_file_name($controller->controllerName),
                    class_to_file_name($controller->controllerName),
                    class_to_file_name($action),
                    class_to_file_name($controller->controllerName),
                    class_to_file_name($action));
        }

        return "";
    }
?>
