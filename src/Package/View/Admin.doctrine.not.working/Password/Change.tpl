{{$response = Package.Raxon.Account:Admin:admin.password.change(flags(), options())}}
{{$response|>object:'json'}}