# Load local variables
source ~/.bashrc_user

export PATH=$PATH:/var/scripts
# If not running interactively, don't do anything
[ -z "$PS1" ] && return
# don't put duplicate lines in the history. See bash(1) for more options
# don't overwrite GNU Midnight Commander's setting of `ignorespace'.
HISTCONTROL=$HISTCONTROL${HISTCONTROL+:}ignoredups
# ... or force ignoredups and ignorespace
HISTCONTROL=ignoreboth
# append to the history file, don't overwrite it
shopt -s histappend

# Color settings
if [ -x /usr/bin/dircolors ]; then
test -r ~/.dircolors && eval "$(dircolors -b ~/.dircolors)" || eval "$(dircolors -b)"
alias ls='ls --color=auto'
fi
export TERM=xterm-256color

# Normal Colors
Black='\e[0;30m'        # Black
Red='\e[0;31m'          # Red
Green='\e[0;32m'        # Green
Yellow='\e[0;33m'       # Yellow
Blue='\e[0;34m'         # Blue
Purple='\e[0;35m'       # Purple
Cyan='\e[0;36m'         # Cyan
White='\e[0;37m'        # White
# Bold
BBlack='\e[1;30m'       # Black
BRed='\e[1;31m'         # Red
BGreen='\e[1;32m'       # Green
BYellow='\e[1;33m'      # Yellow
BBlue='\e[1;34m'        # Blue
BPurple='\e[1;35m'      # Purple
BCyan='\e[1;36m'        # Cyan
BWhite='\e[1;37m'       # White
# Background
On_Black='\e[40m'       # Black
On_Red='\e[41m'         # Red
On_Green='\e[42m'       # Green
On_Yellow='\e[43m'      # Yellow
On_Blue='\e[44m'        # Blue
On_Purple='\e[45m'      # Purple
On_Cyan='\e[46m'        # Cyan
On_White='\e[47m'       # White
# Color reset
NC="\e[m"
# Color2user
if [[ $USER == "root" ]]; then
  SU=$BRed
elif [[ $USER == "cms" ]]; then
    SU=$BGreen
else
    SU=$BCyan
fi
PS1="\[$SU\]\u\[$NC\]@\h:\[$BWhite\]\w\[$NC\]\\$ \[\e]0;\u@\h:\w\a\]"

#ssh-agent
run=0
ps | grep -q ssh-agent || run=1
((run)) && ssh-agent.exe > ~/ssh-agent.sh
source ~/ssh-agent.sh
((run)) && ssh-add ~/.ssh/id_rsa

# GIT
alias gaas='git add -A; gs'
alias gclone='_(){ git clone --recursive ${1:-git@bitbucket.org:igwr/cms.git}; }; _'
alias gd='git diff'
alias gdc='_(){ git log $1^..$1 -p; }; _' # git diff commit [param HASH]
alias gdel='_(){ git branch -d $1 && git push origin :$1; }; _'
alias gc='git commit'
alias gl='git log --decorate --all --oneline --graph'
alias glc='git log --decorate --oneline'
alias gpush='git push --all; git push --tags'
alias gpull='git pull --all --tags && git fetch -p && git submodule update --init --recursive'
alias gpullhard='git reset --hard && gpull'
alias guc='_(){ git reset --soft ${1:-HEAD~1}; }; _'
alias gs='git status && git submodule status'

# BASH
alias sshcms='ssh -i ~/.ssh/id_rsa cms@31.31.75.247'
alias less='less -rSX'
alias ll='ls -lah'
alias phplint='find . -name "*.php" -type f -exec php -l "{}" \;'

# LOCAL
alias cms='cd "$CMS_FOLDER"'
