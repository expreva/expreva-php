<?php
namespace Expreva;

/**
 * A rule represents an instruction for the lexer.
 *
 * Rules can `accept` input strings and produce a `token` if an input has been accepted.
 *
 * The accepted inputs for a rule are defined by the regular expression `$match`, which should
 * include exactly one capturing group.  The value captured by this group is used as the value
 * for the token.  If nothing is captured, the value will be null.
 */
class Rule {

  // Regular expression to match a token
  public $match;

  public function __construct($definition) {
    $this->name = $definition['name'];
    $this->match = $definition['match'];
    $this->definition = $definition;
    return $this;
  }

  /**
   * Try matching the rule at the beginning of `$string`.  A token instance is created from
   * the matched string and returned.  Also returns the length of the match, which is used by
   * the parser to remove the matched text.
   */
  public function accept($string, $line_index, $column_index) {

    $matches = [];

    /**
     * Regexp mode to capture match and its offset
     * @see https://www.php.net/preg_match
     */
    if (preg_match($this->match, $string, $matches, PREG_OFFSET_CAPTURE) !== 1) {
      return;
    }

    // $matches = [ [total_match, offset], [match_1, offset], .. ]

    if (count($matches) < 2) {
      $matches[] = [null, 0]; // (empty)
    }

    $total_match = $matches[0][0];
    $value = $matches[1][0];
    $value_offset = $matches[1][1];

    $definition = array_merge($this->definition, [
      'value' => $value,
      //'total_match' => $total_match,
      'line' => $line_index + 1,
      'column' => $column_index + $value_offset + 1
    ]);

    return [
      'token' => new Token($definition),
      'length' => strlen($total_match)
    ];
  }
}
