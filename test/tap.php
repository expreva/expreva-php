<?php

/*
 * Tester module which emits TAP (Test Anything Protocol) compliant output.
 */
class TAP {
  private static $out = null;
  private $count;
  private $failed;

  public function __construct() {

    if (self::$out === null) {
      self::$out = fopen('php://stdout', 'w');
    }

    $this->init();
  }

  public function init() {
    $this->count  = 0;
    $this->failed = 0;
  }

  function ok($val, $test_name = null) {
    $this->count++;

    if (!$val) {
        $this->failed++;
    }

    fprintf(self::$out, "%sok %d%s\n",
      ($val ? '' : 'not '),
      $this->count,
      ($test_name !== null ? ' - ' . $test_name : '')
    );

    return !!$val;
  }

  function message($msg) {

    $lines = implode("\n",
      array_map(function ($line) {
        return '# ' . $line;
      }, explode("\n", $msg))
    );

    fprintf(self::$out,"# %s\n", $msg);
  }

  function is($got, $expected, $test_name = null) {
    if ($got !== $expected) {
      fprintf(self::$out,"# %10s: '%s'\n# %10s: '%s'\n",
        'got', $got, 'expected', $expected
      );
    }
    $this->ok($got === $expected, $test_name);
  }

  function done() {
    fprintf(self::$out,"1..%d\n", $this->count);
  }
}
