{{$request = request()}}
Package: {{$request.package}}

Module: {{$request.module|>string.uppercase.first}}

{{binary()}} {{$request.package}} admin create:
{{binary()}} {{$request.package}} setup:
