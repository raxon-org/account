{{$response = Package.Raxon.Account:User:setup.anonymous(flags(), options())}}
{{$response|object:'json'}}