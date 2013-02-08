<?php
/**
 * This file is used to update all of the upload paths for a particular site
 * after a database update from a different site has been applied.
 * 
 * It will try to match a supplied partial path and replace it with whatever the
 * user has entered.
 * 
 * @author James McFall <james@mcfall.geek.nz>
 * @version 2.0
 * @date 10 November 2011
 */
# Enter the details for a particular project here
switch($_SERVER["SERVER_NAME"]) {
    
    # Local Environment
    case "mysite.local":
        $mysql_database = "my_db";
        $mysql_username = "me";
        $mysql_password = "monkeys";
        break;
    
}

# Establish DB Connection (Note we're out of CI at the moment so just use old mysql).
$connection = mysql_connect("localhost", $mysql_username, $mysql_password);
mysql_select_db($mysql_database, $connection);

# > php 5.3 doens't have lcfirst() and we need it, so create our own version if not declared
bringLCFirstIntoScope();

# Get the upload paths
$paths = getAllUploadPaths();
$messages = array();
$sectionsSupplied = true;

# Now we want to check if the form has been submitted and start the processing.
if (isset($_POST['submit'])) {

    # Check both of the required fields are supplied
    if (strlen($_POST['replaceThisSection']) == 0 || strlen($_POST['replacementSection']) == 0) {
        $messages['errors'][] = "Please specify a section to replace and a replacement section";
        $sectionsSupplied = false;
    }
        
    if ($sectionsSupplied !== false) {
        # If everything is ok, we can go through and try to update each field.
        foreach ($paths as $pathObject) {
        
            # Check we can find the section to replace in the server path. If not, set an error.
            if (!strstr($pathObject->server_path, $_POST['replaceThisSection'])) {
                $messages['errors'][] = "Path " . $pathObject->server_path . " does not contain ".
                    "the specified section " . $_POST['replaceThisSection'];
                continue;
            }

            # Build replacement path
            $newPath = str_replace($_POST['replaceThisSection'], $_POST['replacementSection'], $pathObject->server_path);

            # Build the query to update this row
            $queryString = "UPDATE `exp_upload_prefs` 
                            SET `server_path` = '" . mysql_real_escape_string($newPath) . "'
                            WHERE `name` = '" . $pathObject->name . "'
                            LIMIT 1";

            # Execute the query
            mysql_query($queryString);
            
            # Add a success message to the messages array
            $messages['success'][] = "Updated " . $pathObject->name;
        }
    }
    

    # Update the paths again to show the new updates
    $paths = getAllUploadPaths();
}
?>
<!DOCTYPE HTML>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>ExpressionEngine Upload Path Updater</title>

        <style>
            body, html {
                margin: 0;
                padding: 0;
                background: #2c2e3f;
                font-family: arial;
                font-size: 12px;
            }
            #shell {
                width: 700px;
                margin: 40px auto;
            }
            #shell h1 {
                color: white;
                text-align: center;
            }
            label {
                width: 140px;
                font-weight: bold;
                height: 25px;
                line-height: 25px;
                vertical-align: middle;
                float: left;
            }
            input[type=text] {
                width: 500px;
                padding:3px;
            }
            input[type=submit] {
                float: right;
            }
            .fieldRow {
                width: 100%;
                overflow: hidden;
                margin: 0 0 10px 0;
            }
            .successMessage {
                color: green;
                border: 1px solid green;
                padding: 10px;
                margin: 10px 0;
                font-weight: bold;
                background: #98c262;
                width: 777px;
            }
            th {
                padding: 6px;
                color: white;
                background: #AAAAEE;
            }
            td {
                padding: 6px;
                background: #ceced7;
            }
            form {
                background: #ceced7;
                padding: 20px;
                margin: 1px 2px;
                overflow: hidden;
            }
            
            #messages {
                background: #ceced7;
                padding: 10px 20px;
                margin: 1px 2px 2px 2px;
            }
            
            table { width: 700px; }
            ul#successMessages { color: green; }
            ul#errorMessages { color: red; }
            ul { padding: 0; margin: 0; }
            
        </style>
    </head>
    <body>
        <div id="shell">
            <h1>ExpressionEngine Upload Path Updater</h1>

            <table>
                <thead>
                    <tr>
                        <th>Channel Name</th>
                        <th>Upload Path</th>
                    </tr>
                </thead>
                <tbody>
                <? foreach ($paths as $pathName => $pathObject): ?>
                    <tr>
                        <td><b><?= $pathName ?>:&nbsp;</b></td>
                        <td><?= $pathObject->server_path ?></td>
                    </tr>
                <? endforeach; ?>
                </tbody>
            </table>

            <? if(!empty($messages['errors']) || !empty($messages['success'])): ?>
            <div id="messages">
                <? if(!empty($messages['errors'])): ?>
                <ul id="errorMessages">
                <? foreach ($messages['errors'] as $error): ?>
                    <li><?=$error?></li>
                <? endforeach; ?>
                </ul>
                <? endif; ?>
                
                <? if(!empty($messages['success'])): ?>
                <ul id="successMessages">
                <? foreach ($messages['success'] as $successMessages): ?>
                    <li><?=$successMessages?></li>
                <? endforeach; ?>
                </ul>
                <? endif; ?>
            </div>
            <? endif; ?>   
                
            <form id="pathUpdateForm" name="pathUpdateForm" action="" method="post">
                <div class="fieldRow">
                    <label for="replaceThisSection">Replace this section:</label>
                    <input type="text" id="replaceThisSection" name="replaceThisSection" 
                           value="<?=(isset($_POST['replaceThisSection']) ? $_POST['replaceThisSection'] : '')?>" />
                </div>
                
                <div class="fieldRow">
                    <label for="replacementSection">With this section:</label>
                    <input type="text" id="replacementSection" name="replacementSection" 
                           value="<?=(isset($_POST['replacementSection']) ? $_POST['replacementSection'] : '')?>" />
                </div>
                <input type="submit" name="submit" value="Update Paths" />
            </form>
        </div>
    </body>
</html>





<?php
################################################################################
## Functions

/**
 * This function grabs all the rows from the exp_upload_prefs, converts them to
 * stdClass objects and adds them to an array.
 * 
 * @return <array> $pathObjects[path_name] = object;
 */
function getAllUploadPaths() {
    # Pull all of the upload paths out of the database to display
    $pathResults = mysql_query("SELECT * FROM `exp_upload_prefs`");
    $pathObjects = array();

    while ($row = mysql_fetch_object($pathResults)) {
        $row->prepped_field_name = lcfirst(str_replace(' ', '', $row->name));
        $pathObjects[$row->name] = $row;
    }

    return $pathObjects;
}

/**
 * lcfirst is only available in php 5.3 (which my local copy isn't at the moment)
 * so just in case, here's a function that mimics it. This needs to go before
 * lcfirst is used so can't go in the functions section.
 */
function bringLCFirstIntoScope() {
    if (function_exists('lcfirst') === false):

        function lcfirst($str) {
            $str[0] = strtolower($str[0]);
            return $str;
        }

    endif;
}
?>