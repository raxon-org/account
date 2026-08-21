{{$request = request()}}
Package: {{$request.package}}

Module: {{$request.module|>string.uppercase.first}}

{{binary()}} {{$request.package}} admin create:
{{binary()}} {{$request.package}} admin email change:
{{binary()}} {{$request.package}} admin password change:
{{binary()}} {{$request.package}} setup:
