<?php
    function js_on_action_load($app)
    {
        if ($app->lastAction['controller'] != null)
        {
            return 
                sprintf('if (typeof app.%s !== "undefined") if (typeof app.%s.%s !== "undefined") eval("app.%s.%s();");',
                    classToFileName($app->lastAction['controller']),
                    classToFileName($app->lastAction['controller']),
                    classToFileName($app->lastAction['action']),
                    classToFileName($app->lastAction['controller']),
                    classToFileName($app->lastAction['action']));
        }
        
        return "";
    }
?>
