<?php
namespace Expreva;

/**
 * The lexer applies a set of rules to a given string, matches regular expressions
 * and generates token instances.
 *
 * @see https://en.wikipedia.org/wiki/Lexical_analysis
 *
 * The order in which rules are added to is important, as it is the order in which
 * they're are matched against the input.
 *
 * Example:
 *
 * ```php
 * $lexer = new Lexer([
 *   [
 *     'name' => 'if',
 *     'match' => '/^if/'
 *   ],
 *   [
 *     'name' => 'identifier',
 *     'match' => '/^([a-zA-Z0-9_]+)/'
 *   ]
 * ]);
 *
 * $lexer->tokenize('if a');
 * ```
 *
 * If the rules in the example were added in reversed order, the rule for `if` would
 * never apply, since the rule for `identifier` also accepts the string `if`.
 */
class Lexer {

  private $rules;   /* ordered list of rules  */

  function __construct($rules = []) {
    foreach ($rules as $rule) {
      $this->rules []= new Rule($rule);
    }
  }

  /**
   * Split `$input` into tokens and return the resulting list.
   */
  public function tokenize($input) {

    $match = '';
    $token = null;
    $tokens = [];

    $lines = explode("\n", $input);

    foreach ($lines as $line_index => $line) {

      $column_index = 0;

      while (strlen($line) > 0) {

        $progressed = false;

        foreach ($this->rules as $rule) {

          $result = $rule->accept($line, $line_index, $column_index);
          if (!$result) continue;

          $length = $result['length'];
          $token = $result['token'];

          $line = substr($line, $length);
          $column_index += $length;
          $tokens[] = $token;
          $progressed = true;
          break;
        }

        if (!$progressed) {
          error_log("Unable to tokenize \"$line\"");
          break;
        }
      }
    }

    $tokens[] = new EndToken();
    return $tokens;
  }
}
