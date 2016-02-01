"pathogen auto load plugins
execute pathogen#infect()
syntax on
filetype plugin indent on

"show line numbers
set number

"set tabs
set expandtab
set shiftwidth=2
set tabstop=2


"set colorscheme
let g:gruvbox_italic=0
colorscheme gruvbox
set background=dark

"highlight search
set hlsearch
set incsearch

"tabs next, prev
nnoremap <C-A-Left> :tabprevious<CR>
nnoremap <C-A-Right> :tabnext<CR>
nnoremap <C-w> :tab close<CR>
nnoremap <C-t> :tabnew<CR>

"autocomplete
function! Tab_Or_Complete()
if col('.')>1 && strpart( getline('.'), col('.')-2, 3 ) =~ '^\w'
  return "\<C-N>"
else
  return "\<Tab>"
endif
endfunction

:inoremap <Tab> <C-R>=Tab_Or_Complete()<CR>
:set dictionary="/usr/dict/words"
