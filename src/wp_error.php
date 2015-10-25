<?php

// Stub function, to prevent breakage from using an external library..
function is_wp_error($thing)
{
    if (is_object($thing) && is_a($thing, 'WP_Error')) {
        return true;
    }
    return false;
}

// WP_Error( 'SimpleXML_parse_error', __( 'There was an error when reading this WXR file', 'wordpress-importer' ), libxml_get_errors() );
class WP_Error
{

    private $error_code;

    function __construct($error_code, $description, $parameters)
    {

        $this->error_code = $error_code;

        echo "<h2>" . $error_code . "</h2>";
        echo "<p>" . $description . "</p>";
        \Dumper::dump($parameters);

        die();

    }

    function get_error_code()
    {
        return $error_code;
    }

}
