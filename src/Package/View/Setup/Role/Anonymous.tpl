{{$response = Package.Raxon.Account:User:setup.role.anonymous(flags(), options())}}
{{$response|object:'json'}}