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

# set local variables (definable in .bash_profile)
[ -z "$CMS_FOLDER" ] && CMS_FOLDER="$HOME/cms"
[ -z "$SU" ] && SU=$BCyan
[ -z "$MYPS1" ] && MYPS1="\[$SU\]\u\[$NC\]@\h:\[$BWhite\]\w\[$NC\]\\$ \[\e]0;\u@\h:\w\a\]"

PS1="$MYPS1"
export PATH=$PATH:/var/scripts
# don't put duplicate lines in the history. See bash(1) for more options
# don't overwrite GNU Midnight Commander's setting of `ignorespace'.
HISTCONTROL=$HISTCONTROL${HISTCONTROL+:}ignoredups
# ... or force ignoredups and ignorespace
HISTCONTROL=ignoreboth
# append to the history file, don't overwrite it
shopt -s histappend

# env color settings
if [ -x /usr/bin/dircolors ]; then
test -r ~/.dircolors && eval "$(dircolors -b ~/.dircolors)" || eval "$(dircolors -b)"
alias ls='ls --color=auto'
fi
export TERM=xterm-256color

#ssh-agent
start=0
ps | grep -q ssh-agent || start=1 # start ssh if not running
((start)) && ssh-agent > ~/ssh-agent.sh # run ssh-agent and save output (variables)
source ~/ssh-agent.sh # register saved variables
((start)) && ssh-add ~/.ssh/id_rsa # add private key on start

# GIT
alias gaas='git add -A; gs'
alias gclone='_(){ git clone --recursive ${1:-git@bitbucket.org:igwr/cms.git}; }; _'
alias gd='git diff'
alias gdc='_(){ git log $1^..$1 -p $2; }; _' # git diff commit [param HASH] of a [param file] (optional)
alias gdel='_(){ git branch -d $1; git push origin :$1; }; _' # git delete branch local & remote [param BRANCH]
alias gc='git commit'
alias gl='git log --decorate --all --oneline --graph' # git log all branches
alias glc='_(){ git log --decorate --oneline ${1~1..:}; }; _' # git log from actual to [commit] including
alias gpush='git push --all; git push --tags'
alias gpull='git pull --all --tags; git fetch -p; git submodule update --init --recursive'
alias gpullhard='git reset --hard && gpull'
alias guc='_(){ git reset --soft ${1:-HEAD~1}; }; _' # git uncommit
alias gup='_(){ git branch -u ${1:-$(echo "origin/$(git status | head -1 | cut -d" " -f 3)")}; }; _' # git track branch
alias gs='git status && git submodule status'

# BASH
alias .='cd ~'
alias ..='cd ..'
alias ...='cd ../..'
alias ....='cd ../../..'
alias ll='ls -lah'
alias less='less -rSX'
alias cms='cd "$CMS_FOLDER"'
alias sshcms='ssh cms@31.31.75.247'
#alias sshcms='ssh -i ~/.ssh/id_rsa cms@31.31.75.247'
alias phplint='find . -name "*.php" -type f -exec php -l "{}" \;'
alias wchtml='wc -w *.html | awk '\''{ print $1-80"\t"$2 }'\'' | sort -k 1n'
