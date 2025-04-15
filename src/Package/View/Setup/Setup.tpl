{{$register = Package.Raxon.Account:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Account:Import:role.system()}}
{{Package.Raxon.Account:User:setup.role.anonymous(flags(), options())}}
{{Package.Raxon.Account:User:setup.role.user(flags(), options())}}
{{Package.Raxon.Account:User:setup.role.system(flags(), options())}}
{{$options = options()}}
/**
 // setup roles*
 // setup permissions*
 // setup jwt* (no patch, only force)
 // setup admin
 // setup user login (api.example.com)

 */
{{/if}}