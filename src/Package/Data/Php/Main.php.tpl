{{R3M}}
{{$options = options()}}
{{$namespace = $options.namespace}}
{{$class = $options.class}}
{{$trait = $options.trait}}
{{$extends = $options.extends}}
{{$implements = $options.implements|default:[]}}
{{$use = $options.use|default:[]}}
{{$constant = $options.constant|default:[]}}
{{$variable.private = $options.variable.private|default:[]}}
{{$variable.protected = $options.variable.protected|default:[]}}
{{$variable.public = $options.variable.public|default:[]}}
{{$traits = $options.trait_use|default:[]}}
{{$function = $options.function|default:[]}}
{{$user.extends = $options.user.extends}}
{{$user.implements = $options.user.implements|default:[]}}
{{$user.variable.private = $options.user.variable.private|default:[]}}
{{$user.variable.protected = $options.user.variable.protected|default:[]}}
{{$user.variable.public = $options.user.variable.public|default:[]}}
{{$user.traits = $options.user.trait_use|default:[]}}
{{$user.use = $options.user.use|default:[]}}
{{$user.function = $options.user.function|default:[]}}
{{$user.constant = $options.user.constant|default:[]}}
{{$variable.private = array.merge($variable.private, $user.variable.private)}}
{{$variable.protected = array.merge($variable.protected, $user.variable.protected)}}
{{$variable.public = array.merge($variable.public, $user.variable.public)}}
{{$implements = array.merge($implements, $user.implements)}}
{{if($user.extends)}}
{{$extends = $user.extends}}
{{/if}}
{{$function = array.merge($function, $user.function)}}
{{$constant = object.merge($constant, $user.constant)}}
{{$traits = array.merge($traits, $user.traits)}}
{{$use = array.merge($use, $user.use)}}
<?php
namespace {{$namespace}};

{{for.each($use as $usage)}}
use {{$usage}};
{{/for.each}}

{{if(
is.empty($class) &&
is.empty($trait)
)}}
{{elseif($trait)}}
trait {{$trait}} {
{{else}}
{{if($implements && $extends)}}
class {{$class}} extends {{$extends}} implements {{implode(', ', $implements)}} {
{{elseif($implements)}}
class {{$class}} implements {{implode(', ', $implements)}} {
{{elseif($extends)}}
class {{$class}} extends {{$extends}} {
{{else}}
class {{$class}} {
{{/if}}
{{/if}}
{{if($constant)}}
{{for.each($constant as $property => $value)}}
{{if(is.array($value))}}
    const {{$property}} = [
        {{implode(',' + "\n        ", $value)}}

    ];
{{else}}
    const {{$property}} = {{$value}};
{{/if}}
{{/for.each}}
{{/if}}
{{if($traits)}}

{{for.each($traits as $trait_use)}}
    use {{$trait_use}};
{{/for.each}}
{{/if}}
{{if($variable.private)}}

{{$variable.private = Package.Raxon.Org.Account:Php:php.variable.define($variable.private, 'private')}}
{{implode("\n", $variable.private)}}
{{/if}}
{{if($variable.protected)}}

{{$variable.protected = Package.Raxon.Org.Account:Php:php.variable.define($variable.protected, 'protected')}}
{{implode("\n", $variable.protected)}}
{{/if}}
{{if($variable.public)}}

{{$variable.public = Package.Raxon.Org.Account:Php:php.variable.define($variable.public, 'public')}}
{{implode("\n", $variable.public)}}
{{/if}}
{{if($function)}}

{{$function = Package.Raxon.Org.Account:Php:php.function.define($function)}}
{{implode("\n", $function)}}
{{/if}}

{{if($class || $trait)}}
}
{{/if}}