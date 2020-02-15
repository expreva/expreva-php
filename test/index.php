<?php
namespace Expreva;

require_once __DIR__.'/tap.php';
$t = new \TAP();

function parse_to_string($expr) {
  $e = expreva();
  return $e->to_string($e->parse($expr));
}

try {

  $e = expreva();

  $t->info('symbolic expressions');

  $t->is(parse_to_string('1 + 2'), '(+ 1 2)', 'simple expression');
  $t->is(parse_to_string('1 + 2 * 3'), '(+ 1 (* 2 3))', 'precendence');
  $t->is(parse_to_string('a = 1'), '(set a 1)', 'assignment');
  $t->is(parse_to_string('1 + 2; 3 + 4'),
    '(do (+ 1 2) (+ 3 4))', 'do'
  );
  $t->is(parse_to_string('(1 + 2) * 3'),
    '(* (+ 1 2) 3)', 'parenthesized subexpressions'
  );

  $t->info('functions');

  $expr = 'f(a,b,c)';
  $t->is(parse_to_string($expr), '(f a b c)', $expr);
  $expr = '1+f(n, m(1+2))';
  $t->is(parse_to_string($expr), '(+ 1 (f n (m (+ 1 2))))', $expr);
  $expr = 'a=>a+b+c';
  $t->is(parse_to_string($expr), '(lambda (a) (+ (+ a b) c))', $expr);
  $expr = '(a,b)=>a+b+c';
  $t->is(parse_to_string($expr), '(lambda (a b) (+ (+ a b) c))', $expr);
  $expr = '(a,b)=>a+b+c';
  $t->is(parse_to_string($expr), '(lambda (a b) (+ (+ a b) c))', $expr);
  $expr = 'f=((a,b)=>a+b);f(2,4)';
  $t->is(parse_to_string($expr), '(do (set f (lambda (a b) (+ a b))) (f 2 4))', $expr);
  $expr = '((a,b)=>a+b)(2,4)';
  $t->is(parse_to_string($expr), '((lambda (a b) (+ a b)) 2 4)', $expr);

  $t->info('function apply');

  $expr = '1->f';
  $t->is(parse_to_string($expr), '(f 1)', $expr);
  $expr = '(1, 2)->f';
  $t->is(parse_to_string($expr), '(f 1 2)', $expr);
  $expr = 'f(2->x=>x+x)';
  $t->is(parse_to_string($expr), '(f ((lambda (x) (+ x x)) 2))', $expr);
  $expr = 'f((2, 3)->(x, y)=>x+y)';
  $t->is(parse_to_string($expr), '(f ((lambda (x y) (+ x y)) 2 3))', $expr);

  $t->info('evaluate');
  $expr = '1 + 1';
  $t->is($e->evaluate($expr), (float) 2, $expr);
  $expr = '2 + 3 * 4';
  $t->is($e->evaluate($expr), (float) 14, $expr);
  $expr = '(2 + 3) * 4';
  $t->is($e->evaluate($expr), (float) 20, $expr);

  $expr = '(x => x)(1)';
  $t->is($e->evaluate($expr), (float) 1, $expr);
  $expr = '(x => x * x)(3)';
  $t->is((float) $e->evaluate($expr), (float) 9, $expr);
  $expr = '((x, y) => x + y)(2, 3)';
  $t->is($e->evaluate($expr), (float) 5, $expr);
  $expr = '((x, y, z) => x + y + z)(2, 3, 4)';
  $t->is($e->evaluate($expr), (float) 9, $expr);

  $t->done();

} catch (\Exception $error) {
  print_r($error instanceof RuntimeError
  ? $error->get_data()
  : [
    'message' => $error->getMessage()
  ]);
}
