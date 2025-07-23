{{$register = Package.Raxon.Account:Setup:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Account:Setup:import.role.system()}}
{{$response = Package.Raxon.Account:Setup:default.create(flags(), options())}}
/*
{{$response = Package.Raxon.Account:Main:account.create.default(flags(), options())}}
{{$response = Package.Raxon.Account:Main:account.create.jwt(flags(), options())}}
{{Package.Raxon.Account:User:setup.role.anonymous(flags(), options())}}
{{Package.Raxon.Account:User:setup.role.user(flags(), options())}}
{{Package.Raxon.Account:User:setup.role.system(flags(), options())}}
{{Package.Raxon.Account:User:setup.role.admin(flags(), options())}}
{{Package.Raxon.Account:Main:setup.permission(flags(), options())}}
{{$options = options()}}
*/
/**
 // setup roles*
 // setup permissions*
 // setup jwt* (no patch, only force)
 // setup admin
 // setup user login (api.example.com)

 */
{{/if}}