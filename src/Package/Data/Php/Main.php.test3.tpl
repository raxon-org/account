{{$options = options()}}
{{$test3 = 4.123}}
{{$test4 = null}}
{{$test = ($options.null|default: 1 + $test3 + ($test4)) + ($options.null2|default:5 + (1))}}
{{$test2 = (object) [
1 => 'test',
2 => (object) [
'test2',
'test3',
],
'nice' => 'very-nice'
]}}
{{$constant = $options.constant2|default:(object) [
'test1' =>  (object) [
'test2' + 'test7' => object.clone($test2), // with  comment
'test2' + 'test9' => (clone) $test2,
'test3' => $raxon.org.parse.compile.url,
'test7' => [
0,
1
]
],
'test5' => 'test6',
'test8' => $test
]}}
{{d($test)}}
{{d($test2)}}
{{d($constant)}}
{{unset($test, $test2)}}
{{d($test)}}
{{d($test2)}}