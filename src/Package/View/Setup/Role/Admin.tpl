{{$response = Package.Raxon.Account:User:setup.role.admin(flags(), options())}}
{{$response|object:'json'}}