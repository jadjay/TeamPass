<?php

// load phpCrypt
if (!isset($_SESSION['settings']['cpassman_dir']) || empty($_SESSION['settings']['cpassman_dir'])) {
    require_once '../includes/libraries/phpcrypt/phpCrypt.php';
} else {
    require_once $_SESSION['settings']['cpassman_dir'] . '/includes/libraries/phpcrypt/phpCrypt.php';
}
use PHP_Crypt\PHP_Crypt as PHP_Crypt;
use PHP_Crypt\Cipher as Cipher;

################
## Function permits to get the value from a line
################
function getSettingValue($val)
{
    $val = trim(strstr($val, "="));
    return trim(str_replace('"', '', substr($val, 1, strpos($val, ";")-1)));
}

################
## Function permits to check if a column exists, and if not to add it
################
function addColumnIfNotExist($db, $column, $columnAttr = "VARCHAR(255) NULL")
{
    global $dbTmp;
    $exists = false;
    $columns = mysqli_query($dbTmp, "show columns from $db");
    while ($c = mysqli_fetch_assoc( $columns)) {
        if ($c['Field'] == $column) {
            $exists = true;
            return true;
        }
    }
    if (!$exists) {
        return mysqli_query($dbTmp, "ALTER TABLE `$db` ADD `$column`  $columnAttr");
    } else {
        return false;
    }
}

function addIndexIfNotExist($table, $index, $sql ) {
    global $dbTmp;

    $mysqli_result = mysqli_query($dbTmp, "SHOW INDEX FROM $table WHERE key_name LIKE \"$index\"");
    $res = mysqli_fetch_row($mysqli_result);

    // if index does not exist, then add it
    if (!$res) {
        $res = mysqli_query($dbTmp, "ALTER TABLE `$table` " . $sql);
    }

    return $res;
}

function tableExists($tablename, $database = false)
{
    global $dbTmp;

    $res = mysqli_query($dbTmp,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '".$_SESSION['db_bdd']."'
        AND table_name = '$tablename'"
    );

    if ($res > 0) return true;
    else return false;
}

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

/*
 * cryption() - Encrypt and decrypt string based upon phpCrypt library
 *
 * Using AES_128 and mode CBC
 *
 * $key and $iv have to be given in hex format
 */
function cryption_phpCrypt($string, $key, $iv, $type)
{
    // manage key origin
    if (empty($key)) $key = SALT;

    if ($key != SALT) {
        // check key (AES-128 requires a 16 bytes length key)
        if (strlen($key) < 16) {
            for ($x = strlen($key) + 1; $x <= 16; $x++) {
                $key .= chr(0);
            }
        } else if (strlen($key) > 16) {
            $key = substr($key, 16);
        }
    }

    // load crypt
    $crypt = new PHP_Crypt($key, PHP_Crypt::CIPHER_AES_128, PHP_Crypt::MODE_CBC);

    if ($type == "encrypt") {
        // generate IV and encrypt
        $iv = $crypt->createIV();
        $encrypt = $crypt->encrypt($string);
        // return
        return array(
            "string" => bin2hex($encrypt),
            "iv" => bin2hex($iv),
            "error" => empty($encrypt) ? "ERR_ENCRYPTION_NOT_CORRECT" : ""
        );
    } else if ($type == "decrypt") {
        // case if IV is empty
        if (empty($iv))
            return array(
                'string' => "",
                'error' => "ERR_ENCRYPTION_NOT_CORRECT"
            );

        // convert
        try {
            $string = testHex2Bin(trim($string));
            $iv = testHex2Bin($iv);
        }
        catch (Exception $e) {
            // error - $e->getMessage();
            return array(
                'string' => "",
                'error' => "ERR_ENCRYPTION_NOT_CORRECT"
            );
        }

        // load IV
        $crypt->IV($iv);
        // decrypt
        $decrypt = $crypt->decrypt($string);
        // return
        //return str_replace(chr(0), "", $decrypt);
        return array(
            'string' => str_replace(chr(0), "", $decrypt),
            'error' => ""
        );
    }
}

function testHex2Bin ($val)
{
    if (!@hex2bin($val)) {
        throw new Exception("ERROR");
    }
    return hex2bin($val);
}