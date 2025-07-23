{{$response = Package.Raxon.Account:User:setup.role.system(flags(), options())}}
{{$response|object:'json'}}