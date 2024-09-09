    <?php
/**
 * @author          Remco van der Velde
 * @since           2020-09-19
 * @copyright       Remco van der Velde
 * @license         MIT
 * @version         1.0
 * @changeLog
 *     -            all
 */
use Raxon\Org\Module\Parse;
use Raxon\Org\Module\Data;

function function_variable_define(Parse $parse, Data $data, $array=[], $type='private'){
    foreach($array as $variable){
        if(!is_object($variable)){
            continue;
        }
        if(!property_exists($variable, 'name')){
            continue;
        }
        if(property_exists($variable, 'doc_comment')){
            echo '/**' . PHP_EOL;
            echo ' * ' . $variable->doc_comment . PHP_EOL;
            echo ' */' . PHP_EOL;
        }
        echo $type . ' ';
        if(property_exists($variable, 'static')){
            echo 'static ';
        }
        if(property_exists($variable, 'type')){
            echo $variable->type . ' ';
        }
        echo $variable->name;
        if(!property_exists($variable, 'value')){
             echo ';' . PHP_EOL;
        } else {
            if(is_null($variable->value)){
                echo ' = null;' . PHP_EOL;
            }
            elseif($variable->value === true){
                echo ' = true;' . PHP_EOL;
            }
            elseif($variable->value === false){
                echo ' = false;' . PHP_EOL;
            }
            elseif(is_array($variable->value)){
                if(empty($variable->value)){
                    echo ' = []';
                } else {
                    echo ' = [' . PHP_EOL;
                    foreach($variable->value as $key => $value){
                        if(is_numeric($key)){
                            if(is_null($value)){
                                echo '    null,' . PHP_EOL;
                            }
                            elseif($value === true){
                                echo '    true,' . PHP_EOL;
                            }
                            elseif($value === false){
                                echo '    false,' . PHP_EOL;
                            }
                            elseif(is_array($value)){
                            }
                            else {
                                echo '    ' . $value . ',' . PHP_EOL;
                            }
                        } else {
                            echo '    ' . $key . ' => ' . $value . ',' . PHP_EOL;
                        }
                    }
                    echo '];' . PHP_EOL;
                }
            } else {
                echo ' = ' . $variable->value . ';' . PHP_EOL;
            }
        }
    }
}
