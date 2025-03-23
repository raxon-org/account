{{$response = Package.Raxon.Account:User:setup.role.user(flags(), options())}}
{{$response|object:'json'}}