<?php

namespace Bolt\Extension\Bolt\Importwxr;

class WP_Error
{
    private $error_code;

    function __construct($error_code, $description, $parameters = null)
    {

        $this->error_code = $error_code;

        echo "<h2>" . $error_code . "</h2>";
        echo "<p>" . $description . "</p>";
        dump($parameters);

        die();
    }

    function get_error_code()
    {
        return $this->error_code;
    }
}
