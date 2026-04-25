{{$request = request()}}
Package: {{$request.package}}

Module: {{$request.module|>string.uppercase.first}}

{{binary()}} {{$package}} admin create:
{{binary()}} {{$package}} setup:
