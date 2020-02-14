<?php
namespace Expreva;

/**
 * This is a Lisp interpreter, based on [miniMAL](https://github.com/kanaka/miniMAL) ported to PHP.
 *
 * Aside from the main functionality (environment, lambda, macro, conditions, tail-call optimization),
 * there are classes that support an object model similar to JavaScript, with first-class functions.
 *
 * - A "runtime object" can have dynamically added functions as properties. The environment is
 * an instance of such an object.
 * - A "runtime function" is an object that can have properties assigned.
 * - A "runtime error" that can throw and catch objects, instead of just strings.
 */

/**
 * Object with dynamic methods
 */
class RuntimeObject {
  public $name = 'object';

  function __construct($arr = [], $env = null) {
    foreach ($arr as $key => $value) {
      $this->$key = $value;
    }
    $this->env = $env ? $env : $this;
  }

  function __call( $method = '', $args = [] ) {
    if ( isset( $this->$method ) ) {
      return call_user_func_array( $this->$method, $args );
    }
    $this->env->throw([
      'message' => "Undefined method \"$method\" for {$this->name}",
    ]);
  }

  function __set( $name, $value ) {
    $this->$name = $value instanceof Closure ? $value->bindTo( $this->env, $this->env ) : $value;
  }
}

/**
 * Function as callable object with dynamic properties
 */
class RuntimeFunction extends RuntimeObject {
  public $name = 'function';
  private $__invoke;

  function __construct($fn, $env) {
    $this->__invoke = $fn;
    parent::__construct([], $env);
  }

  function __invoke() {
    return call_user_func_array($this->__invoke, func_get_args());
  }
}

/**
 * Error with data
 */
class RuntimeError extends \Exception {
  function __construct($message = '', $code = 0,  \Exception $previous = null, $data = []) {
    parent::__construct($message, $code, $previous);
    $this->data = $data;
  }
  function get_data() {
    return $this->data;
  }
}

class RuntimeEnvironment extends RuntimeObject {
  public $name = 'environment';

  // See at bottom of file for definition
  static $core;
  static $default_env;
}


/**
 * Evaluate
 */

function bind_env($args, $parent_env, $given_args) {

  $env = clone $parent_env;

  foreach ($args as $i => $arg) {
    if ($arg==='&') {
      $env->{$args[ $i + 1 ]} = array_slice($given_args, $i);
      break;
    }
    $env->$arg = isset($given_args[ $i ])
      ? $given_args[ $i ]
      : null // TODO: Default argument value
    ;
  }
  return $env;
}

function eval_ast($ast, $env) {
  return is_array($ast)                      // List?
    ? array_map(function($node) use ($env) { // List
      return evaluate($node, $env);
    }, $ast)
    : (is_string($ast)                       // Symbol?
      ? (isset($env->$ast)                   // Symbol in env?
        ? $env->$ast                         // Lookup symbol
        : $env->throw([                      // Undefined symbol
            'message' => "Undefined symbol \"$ast\""
          ])
      )
      : $ast                                 // Unchanged
    )
  ;
}

function expand_macro($ast, $env) {
  while (is_array($ast)
    && !is_array($ast[0])
    && isset($env->{$ast[0]})
    && $env->{$ast[0]} instanceof RuntimeFunction
    && isset($env->{$ast[0]}->is_macro)
  ) {
    $ast = call_user_func_array($env->{$ast[0]}, array_slice($ast, 1));
  }
  return $ast;
}


function evaluate($ast, $given_env = null) {

  $env = !empty($given_env) ? $given_env
    : RuntimeEnvironment::$default_env
  ;

  while (true) {

    if (!is_array($ast)) return eval_ast($ast, $env);

    $ast = expand_macro($ast, $env);

    if (!is_array($ast)) return eval_ast($ast, $env);

    switch ($ast[0]) {

      // Set a variable in current environment
      case 'set':
      // case 'def':
        return $env->{$ast[1]} = evaluate($ast[2], $env);

      // Mark as macro
      case '~':
      case 'macro':
        $f = new RuntimeFunction(evaluate($ast[1], $env), $env);  // Evaluates to regular function
        $f->is_macro = true;
        return $f;

      // Quote (unevaluated)
      case 'expr': return $ast[1];

      // Get or set an array or object attribute
      case '.-':
        $el = eval_ast(array_slice($ast, 1), $env);
        $x = $el[0][ $el[1] ];
        return isset($el[2]) ? $el[0][ $el[1] ] = $el[2] : $x;

      // Call object method
      case '.':
        $el = eval_ast( array_slice($ast, 1), $env);
        $x = $el[0][ $el[1] ];

        // JavaScript: x.apply(el[0], el.slice(2))
        return call_user_func_array($x->bindTo($el[0]), array_slice($el, 2));

      // Try / Catch
      case 'try':
        try {
          return evaluate($ast[1], $env);
        } catch (\Exception $error) {
          return evaluate($ast[2][2], bind_env([ $ast[2][1] ], $env, [
            $error instanceof RuntimeError
              ? $error->get_data()
              : [
                'message' => $error->getMessage()
              ]
          ]));
        }

      // Define new function (lambda)
      case 'fn':
        $f = new RuntimeFunction(function() use ($ast, $env) {
          return evaluate($ast[2], bind_env($ast[1], $env, func_get_args()));
        }, $env);

        $f->ast = [ $ast[2], $env, $ast[1] ];
        return $f;

      // Tail-call optimization cases

      // New environment with bindings
      case 'let':

        $env = clone $env;

        foreach ($ast[1] as $i => $value) {
          if ($i % 2) {
            $env->{$ast[1][ $i - 1 ]} = evaluate($ast[1][ $i ], $env);
          }
        }

        $ast = $ast[2];
        continue 2; // while(true)

      // Multiple forms for side-effects
      case 'do':

        $last = count($ast) - 1;
        if ($last===0) return; // No arguments

        // PHP: array_slice(arr, start, length) - JavaScript: Array.slice(start, end)
        $length = $last - 1;
        eval_ast(array_slice($ast, 1, $length), $env);

        // Tail
        $ast = $ast[ $last ];

        continue 2; // while(true)

      // Conditional branches
      case 'if':
        if (!isset($ast[1])) $env->throw([
          'message' => 'No condition for if',
        ]);
        if (!isset($ast[2])) $env->throw([
          'message' => 'No true branch for if',
        ]);
        if (!isset($ast[3])) {
          // No else branch
          if (!evaluate($ast[1], $env)) return;
          $ast = $ast[2];
          continue 2; // while(true)
        }
        $ast = evaluate($ast[1], $env) ? $ast[2] : $ast[3];
        continue 2; // while(true)
    }

    // Invoke list form

    $el = eval_ast($ast, $env);
    if (empty($el) || empty($el[0])) return;

    $f = $el[0];

    if (is_array($f) && $f[0]==='fn') {
      // Function in environment defined as list form
      $ast = $f[2];
      $env = bind_env($f[1], $env, array_slice($el, 1));
      continue;
    }

    if ($f instanceof RuntimeFunction && isset($f->ast)) {
      $ast = $f->ast[0];
      $env = bind_env($f->ast[2], $f->ast[1], array_slice($el, 1));
      continue;
    }

    if ($f instanceof \Closure) {
      return call_user_func_array($f->bindTo($env), array_slice($el, 1));
    }

    // Calling anything other than function
    return; // $f Return self?
  }
}

RuntimeEnvironment::$core = [
  'true' => true,
  'false' => false,

  '+' => function($a, $b) { return $a + $b; },
  '-' => function($a, $b) { return $a - $b; },
  '*' => function($a, $b) { return $a * $b; },
  '/' => function($a, $b) { return $a / $b; },

  '==' => function($a, $b) { return $a === $b; },
  '!=' => function($a, $b) { return $a !== $b; },
  '<' => function($a, $b) { return $a < $b; },
  '<=' => function($a, $b) { return $a <= $b; },
  '>' => function($a, $b) { return $a > $b; },
  '>=' => function($a, $b) { return $a >= $b; },

  'list' => function() { return func_get_args(); }, // ['fn', ['&', 'a'], 'a']
  'map' => function($arr, $fn) { return array_map($fn, $arr); },

  'eva' => function($a) { return evaluate($a, $this); },
  'throw' => function($a) { throw new RuntimeError('', 0, null, $a); },
  'print' => function() {

    // TODO: Formatted output

    foreach (func_get_args() as $arg) {
      print_r($arg);
    }
  },
];

function create_environment($env = []) {
  return new RuntimeEnvironment(
    array_merge(RuntimeEnvironment::$core, $env)
  );
};

RuntimeEnvironment::$default_env = create_environment();
