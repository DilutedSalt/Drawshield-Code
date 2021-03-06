<?php /* Copyright 2010 Karl R. Wilcox

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License. */

//
// Global Variables
//
$options = array();
include 'version.inc';
/**
 * @var DOMDocument $dom
 */
$dom = null;
/**
 * @var DOMXpath $xpath
 */
$xpath = null;
/**
 * @var messageStore $messages
 */
$messages = null;

$spareRoom = str_repeat('*', 1024 * 1024);

function calculateAR($ar) {
  if (strpos($ar, ':') > 0) {
    $arParts = explode(':', $ar);
    $numerator = intval($arParts[0]);
    $denominator = 0;
    if (count($arParts > 1)) {
      $denominator = intval($arParts[1]);
    }
    if ($denominator == 0) $denominator = 1;
    $ar = $numerator / $denominator;
  } else {
    $ar = floatval($ar);
  }
  if ($ar > 1.2) {
    $ar = 1.2;
  } elseif ($ar < 0.25) {
    $ar = 0.25;
  }
  return $ar;  
}

//
// Argument processing
//
if ( isset($argc) and  $argc > 1 ) { // run in debug mode, probably
  $options['blazon'] = implode(' ', array_slice($argv,1));
  // $options['printable'] = true;
   $options['outputFormat'] = 'png';
}

// Process arguments
$ar = null;
$size = null;
// For backwards compatibility we support argument in GET, but prefer POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_FILES['blazonFile']) && ($_FILES['blazonFile']['name'] != "")) {
    $fileName = $_FILES['blazonFile']['name'];
    $fileSize = $_FILES['blazonFile']['size'];
    $fileTmpName  = $_FILES['blazonFile']['tmp_name'];
    $fileType = $_FILES['blazonFile']['type'];
    if (preg_match('/.txt$/i', $fileName) && $fileSize < 1000000) {
      $options['blazon'] = file_get_contents($fileTmpName);
    } 
  } else {    
      if (isset($_POST['blazon'])) $options['blazon'] = html_entity_decode(strip_tags(trim($_POST['blazon'])));
  }
  if (isset($_POST['outputformat'])) $options['outputFormat'] = strip_tags ($_POST['outputformat']);;
  if (isset($_POST['saveformat'])) $options['saveFormat'] = strip_tags ($_POST['saveformat']);;
  if (isset($_POST['asfile'])) $options['asFile'] = ($_POST['asfile'] == "1");
  if (isset($_POST['palette'])) $options['palette'] = strip_tags($_POST['palette']);
  if (isset($_POST['shape'])) $options['shape'] = strip_tags($_POST['shape']);
  if (isset($_POST['stage'])) $options['stage'] = strip_tags($_POST['stage']);
  if (isset($_POST['printable'])) $options['printable'] = ($_POST['printable'] == "1");
  if (isset($_POST['effect'])) $options['effect'] = strip_tags($_POST['effect']);
  if (isset($_POST['size'])) $size = strip_tags ($_POST['size']);
  if (isset($_POST['ar'])) $ar = strip_tags ($_POST['ar']);
} else { // for old API
  if (isset($_GET['blazon'])) $options['blazon'] = html_entity_decode(strip_tags(trim($_GET['blazon'])));
  if (isset($_GET['saveformat'])) $options['saveFormat'] = strip_tags ($_GET['saveformat']);;
  if (isset($_GET['outputformat'])) $options['outputFormat'] = strip_tags ($_GET['outputformat']);;
  if (isset($_GET['asfile'])) $options['asFile'] = ($_GET['asfile'] == "1");
  if (isset($_GET['palette'])) $options['palette'] = strip_tags($_GET['palette']);
  if (isset($_GET['shape'])) $options['shape'] = strip_tags($_GET['shape']);
  if (isset($_GET['stage'])) $options['stage'] = strip_tags($_GET['stage']);
  if (isset($_GET['printable'])) $options['printable'] = ($_GET['printable'] == "1");
  if (isset($_GET['effect'])) $options['effect'] = strip_tags($_GET['effect']);
  if (isset($_GET['size'])) $size = strip_tags ($_GET['size']);
  if (isset($_GET['ar'])) $ar = strip_tags ($_GET['ar']);
}
if ($size != null) {
  if ( $size < 100 ) $size = 100;
  $options['size'] = $size;
}
if ($ar != null) {
  $options['aspectRatio'] = calculateAR($ar);
}
$options['flagHeight'] = (int)(round($options['aspectRatio'] * 1000));
$options['drawType'] = 'flag';

// Quick response for empty blazon
if ( $options['blazon'] == '' ) {
  include "svg/shapes.inc";
  $displayScale = $options['size'] / 1000;
  $displayWidth = $options['size'];
  $displayHeight = (int)(($options['flagHeight'] + 40) * $displayScale);
  $textY = ($options['flagHeight'] + 34);
  header('Content-Type: text/xml; charset=utf-8');
  $output = '<?xml version="1.0" encoding="utf-8" ?>';
  $output .= '<svg version="1.1" baseProfile="full" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMidYMid meet" height="' .
    $displayHeight . '" width="' .  $displayWidth . '" viewBox="0 0 1000 ' . ($options['flagHeight'] + 40)  . '" >';
  $output .= '<g><desc>grey</desc><title>Shield</title><g fill="#EEEEEE">';
  $output .= '<rect x="0" y="0" width="1000" height="' . $options['flagHeight']  . '" ><title>Field</title></rect></g></g>';
  $output .= '<text x="180" y="' . $textY  . '" font-size="20" >'
   . $version['name'] . ' ' . $version['release'] . '</text><text x="10" y="' . $textY . '" font-size="20" >' . $version['flagsite'] . '</text></svg>';
} else {
  // Otherwise log the blazon for research... (unless told not too)
 if ( $options['logBlazon']) error_log($options['blazon']);

 register_shutdown_function(function()
    {
        global $options, $spareRoom;
        $spareRoom = null;
        // if ((!is_null($err = error_get_last())) && (!in_array($err['type'], array (E_NOTICE, E_WARNING))))
        if (!is_null($err = error_get_last()))
        {
           error_log($options['blazon']);
        }
    });

  include "parser/parser.inc";
  $p = new parser('english');
  $dom = $p->parse($options['blazon'],'dom');
  $p = null; // destroy parser
  if ( $options['stage'] == 'parser') { 
      $note = $dom->createComment("Debug information - parser stage.\n(Did you do SHIFT + 'Save as File' by accident?)");
      $dom->insertBefore($note,$dom->firstChild);
      header('Content-Type: text/xml; charset=utf-8');
      echo $dom->saveXML(); 
      exit; 
  }
  // Resolve references
  include "analyser/utilities.inc";
  include "analyser/references.inc";
  $references = new references($dom);
  $dom = $references->setReferences();
  $references = null; // destroy references
  if ( $options['stage'] == 'references') { 
      $note = $dom->createComment("Debug information - references stage.\n(Did you do SHIFT + 'Save as File' by accident?)");
      $dom->insertBefore($note,$dom->firstChild);
      header('Content-Type: text/xml; charset=utf-8');
      echo $dom->saveXML(); 
      exit; 
  }
  // Add dictionary references
  include "analyser/addlinks.inc";
  $adder = new linkAdder($dom);
  $dom = $adder->addLinks();
  $adder = null; // destroy adder
  if ( $options['stage'] == 'links') { 
      $note = $dom->createComment("Debug information - links stage.\n(Did you do SHIFT + 'Save as File' by accident?)");
      $dom->insertBefore($note,$dom->firstChild);
      header('Content-Type: text/xml; charset=utf-8');
      echo $dom->saveXML(); 
      exit; 
  }

  // Read in the drawing code  ( All formats start out as SVG )
  $xpath = new DOMXPath($dom);
  //include "analyser/rewriter.inc";
  // some fudges / heraldic knowledge
  // rewrite();
  // if ( $options['stage'] == 'rewrite') { 
  //     $note = $dom->createComment("Debug information - rewrite stage.\n(Did you do SHIFT + 'Save as File' by accident?)");
  //     $dom->insertBefore($note,$dom->firstChild);
  //     header('Content-Type: text/xml; charset=utf-8');
  //     echo $dom->saveXML(); 
  //     exit; 
  // }
  include "svg/draw-f.inc";
  $output = draw();
}

// Output content header
if ( $options['asFile'] ) {
  switch ($options['saveFormat']) {
    case 'svg':
     header("Content-type: application/force-download");
     header('Content-Disposition: inline; filename="flag.svg"');
     header("Content-Transfer-Encoding: text");
     header('Content-Disposition: attachment; filename="flag.svg"');
     header('Content-Type: image/svg+xml');
     echo $output;
     break;
    case 'jpg':
      $im = new Imagick();
      $im->readimageblob($output);
      $im->setimageformat('jpeg');
      $im->setimagecompressionquality(90);
      // $im->scaleimage(1000,1200);
      header("Content-type: application/force-download");
      header('Content-Disposition: inline; filename="flag.jpg"');
      header("Content-Transfer-Encoding: binary");
      header('Content-Disposition: attachment; filename="flag.jpg"');
      header('Content-Type: image/jpg');
      echo $im->getimageblob();
      break;
    case 'png':
    default:
     $im = new Imagick();
     $im->setBackgroundColor(new ImagickPixel('transparent'));
     $im->readimageblob($output);
     $im->setimageformat('png32');
     // $im->scaleimage(1000,1200);
     header("Content-type: application/force-download");
     header('Content-Disposition: inline; filename="flag.png"');
     header("Content-Transfer-Encoding: binary");
     header('Content-Disposition: attachment; filename="flag.png"');
     header('Content-Type: image/png');
     echo $im->getimageblob();
     break;
   }
} else {
  switch ($options['outputFormat']) {
    case 'jpg':
      $im = new Imagick();
      $im->readimageblob($output);
      $im->setimageformat('jpeg');
      $im->setimagecompressionquality(90);
      // $im->scaleimage(1000,1200);
      header('Content-Type: image/jpg');
      echo $im->getimageblob();
      break;
    case 'png':
      $im = new Imagick();
      $im->setBackgroundColor(new ImagickPixel('transparent'));
      $im->readimageblob($output);
      $im->setimageformat('png32');
      // $im->scaleimage(1000,1200);
      header('Content-Type: image/png');
      echo $im->getimageblob();
      break;
    default:
    case 'svg':
      header('Content-Type: text/xml; charset=utf-8');
      echo $output;
      break;
  }
}

