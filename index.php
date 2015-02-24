<html>
<head>Parse Test</head>
<body><h1>TESTING PARSE WITH AWESOME PHP</h1>
<?php 
//define('PARSE_SDK_DIR', './Parse/' );

// include Parse SDK autoloader
require_once('autoload.php');

use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseACL;
use Parse\ParsePush;
use Parse\ParseUser;
use Parse\ParseInstallation;
use Parse\ParseException;
use Parse\ParseAnalytics;
use Parse\ParseFile;
use Parse\ParseCloud;
use Parse\ParseClient;

ParseClient::initialize( 'lSII6Ttxzch0rEHIBhEJxt32SsCtibaDKDHm2AFa', 'WaMEFrz2cjWWxoE7f46IF3uZUP0GRHzEWApJ4nmB', '90GXpoo86rIvgc5W0bC5BzqflCLNq3PxV6qpqRHh' );
$query = new ParseQuery("ingrediente");
$query->equalTo("nombre", "Limon");
$results = $query->find();
echo "Successfully retrieved " . count($results) . " scores.";
// Do something with the returned ParseObject values
for ($i = 0; $i < count($results); $i++) { 
  $object = $results[$i]; ?>
  <ul>
  	<li>
  		<?php echo $object->getObjectId() . ' - ' . $object->get('descripcion') . '-' . $object->get('nombre'); ?>
  	</li>
  </ul>
<?php } ?>
</body></html>