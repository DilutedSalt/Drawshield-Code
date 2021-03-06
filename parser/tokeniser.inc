<?php /* Copyright 2014 Karl R. Wilcox

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License. */

class tokeniser {

  public $words = array(); // Used internally to manage tokens
  public $lineNos = array();
  public $badWords = false; // Set if unknown token encountered
  public $cur_word;
  public $num_tokens;
  // Constructor
  // Fill the words array with words from the blazon
  // Punctuation and spaces ignored
  function __construct($blazonText) {
    
    $blazon = iconv("UTF-8", "ASCII//TRANSLIT", $blazonText);
    $this->words = array();
    $this->lineNos = array();
    $this->cur_word = 0;
    $lineNo = 1;
  
    $i = 0;
    $lineComment = false;
    $comment = false;
    $in_string = false;
    $incAll = false;
    $given = false;
    $part_word = '';
    $end = strlen($blazon);
    $lookBehind = '';
    while ( $i < $end ) {
      $lookAhead = '';
      if ($i + 1 < $end) {
        $lookAhead = $blazon[$i+1];
      }
      switch ( $blazon[$i] ) {
        case "\n":
        case ' ':
        case "\t":
        case "\r":
          if ( $incAll )
            $part_word .= ' ';
          elseif ( $part_word != '' ) {
            if ( !$comment ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            $part_word = '';
          } // else ignore it
          if ($blazon[$i] == "\n") {
            $lineNo += 1;
            if ($lineComment) {
              $lineComment = false;
              $comment = false;
            }
          }
          break;
        case '{':
          if ( $incAll ) {
            $part_word .= $blazon[$i];
            break;
          }
          if ( $part_word != '' ) {
            if ( !$comment ) { $this->words[] = $part_word;  $this->lineNos[] = $lineNo; }
          }
          $part_word = '{';
          $incAll = true;
          break;
        case '}':
          if ( $incAll ) {
            $part_word .= $blazon[$i];
            break;
          }
          if ( $part_word != '' ) {
            $part_word .= '}';
            $this->words[] = $part_word;
            $this->lineNos[] = $lineNo;
          }
          $part_word = '';
          $incAll = false;
          break;
        case '(':
        case '[':
          if ( $incAll ) {
            $part_word .= $blazon[$i];
            break;
          }
          if ( $part_word != '' ) {
            $this->words[] = $part_word; 
            $this->lineNos[] = $lineNo;
            $part_word = '';
          }
          $comment = $blazon[$i];
          break;
        case '*':
          if ( $incAll ) {
            $part_word .= $blazon[$i];
            break;
          }
          if ('*' == $comment && $lookAhead == '/') { // match bracket type
            $comment = false;
            $part_word = '';
            $i += 1;
          } else {
            if ( $part_word != '' ) {
              if ( $part_word != '' ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            }
            $this->words[] = '*';
            $this->lineNos[] = $lineNo;
            $part_word = '';
          }
          break;
        case ')':
          if ( $incAll ) {
            $part_word .= $blazon[$i];
            break;
          }
          if ($comment == '(') { // match bracket type
            $comment = false;
            $part_word = '';
          } else {
            if ( $part_word != '' ) {
              if ( $part_word != '' ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            }
            $this->words[] = $blazon[$i];
            $this->lineNos[] = $lineNo;
            $part_word = '';
          }
          break;
        case ']':
          if ( $incAll ) {
            $part_word .= $blazon[$i];
            break;
          }
          if ($comment == '[') { // match bracket type
            $comment = false;
            $part_word = '';
          } else {
            if ( $part_word != '' ) {
              if ( $part_word != '' ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            }
          }
          break;
        case '/':
          if ( $incAll ) {
            $part_word .= '/';
            break;
          }
          if ( $part_word != '' ) {
            if ( !$comment ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            $part_word = '';
          }
          if ($lookAhead == '/') {
            $lineComment = true;
            $comment = true;
            $i += 1;
          } elseif ($lookAhead == '*') {
            $comment = '*';
            $i += 1;
          }
          break;
         case '#':
          if ( $incAll ) {
            $part_word .= '#';
            break;
          }
          if ( $part_word != '' ) {
            if ( !$comment ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            $part_word = '';
          }
          $lineComment = true;
          $comment = '#';
          break;
        case '-':
          if ( $incAll ) {
            $part_word .= ' ';
            break;
          }
          if ( $part_word != '' ) {
            if ( !$comment ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            $part_word = '';
          }
          if ($comment) break;
          if ( $i > 0 and strstr(  " \t\n\r'\",.", $blazon[$i-1])) {
            $i = $end;
          }
          if ( $i < $end and $blazon[$i+1] == '-' ) {
            $i = $end;
          }
          break;
        case '.':
        case ';':
        case ',':
        case ':':
          if ( $incAll ) {
            $part_word .= $blazon[$i];
            break;
          }
          if ($blazon[$i] == ':' && ctype_digit($lookBehind) && ctype_digit($lookAhead)) {
            $part_word .= $blazon[$i];
            break;
          } elseif ( $part_word != '' ) {
            if ( !$comment ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            $part_word = '';
          }
          if ( !$comment ) { $this->words[] = $blazon[$i]; $this->lineNos[] = $lineNo; }
          break;
        case '"':
          if ( $in_string ) {
            if ( $part_word != '' ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
            $part_word = '';
            $in_string = false;
            $incAll = false;
          } else {
            // $part_word = '"';
            $incAll = true;
            $in_string = true;
          }
          break;
        case '\\':
          if ( $incAll ) {
            if ( $lookAhead == '"' ) {
              $part_word .= '"';
              $i++;

            } else
              $part_word .= '\\';
          }
          break;
        case "'":
        case '`':
        case '~':
        case '^':
          if ( $incAll ) {
            $part_word .= $blazon[$i];
          }
          break;
        default:
          $part_word .= $blazon[$i];
          break;
      }
      $lookBehind = $blazon[$i];
      $i++;
    }
    if ( $part_word != '' ) {
      if ( !$comment ) { $this->words[] = $part_word; $this->lineNos[] = $lineNo; }
    }
    $this->num_tokens = count($this->words);
    $this->origWords = $this->words;
  }
  
  public function ignoreWord($offset) {
    global $trace;
    
    $this->badWords = true;
    if ( $offset >= $this->num_tokens )
      $offset = $this->num_tokens - 1;
    elseif ( $offset < 0 )
      $offset = 0;
    // in case already ignored, try ignoring next word
    while ( $this->words[$offset]{0} == '[' and $offset < $this->num_tokens  )
      $offset++;
    $this->words[$offset] = '[' . $this->words[$offset] . ']';
    if ($trace) echo "<p>Ignoring - " . $this->words[$offset] . "</p>\n";
  }

  public function restoreWord($offset) {
    global $trace;


    if ( $offset >= $this->num_tokens or $offset < 0 ) return;
    $this->words[$offset] = trim($this->words[$offset],'[]');

    if ($trace) echo "<p>Restoring - " . $this->words[$offset] . "</p>\n";
  }
  
  public function resetToken() {
    global $trace;
    
    if ($trace) echo "<p>RESET TOKENS</p>\n"; // DEBUG
    $this->cur_word = 0;
  }
  
  public function moreInput() {
    return ( $this->cur_word < $this->num_tokens );
  }
  
  public function getTokens() {
    return $this->words;
  }
  
  public function getLineNos() {
    return $this->lineNos;
  }
  
}
     
?>
