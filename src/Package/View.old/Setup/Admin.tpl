{{$response = Package.Raxon.Account:User:setup.admin(flags(), options())}}
{{$response|object:'json'}}