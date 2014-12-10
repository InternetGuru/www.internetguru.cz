# ~/.bashrc: executed by bash(1) for non-login shells.
# see /usr/share/doc/bash/examples/startup-files (in the package bash-doc)
# for examples

# If not running interactively, don't do anything
[ -z "$PS1" ] && return

# don't put duplicate lines in the history. See bash(1) for more options
# don't overwrite GNU Midnight Commander's setting of `ignorespace'.
HISTCONTROL=$HISTCONTROL${HISTCONTROL+:}ignoredups
# ... or force ignoredups and ignorespace
HISTCONTROL=ignoreboth

# append to the history file, don't overwrite it
shopt -s histappend

# for setting history length see HISTSIZE and HISTFILESIZE in bash(1)

# check the window size after each command and, if necessary,
# update the values of LINES and COLUMNS.
shopt -s checkwinsize

# make less more friendly for non-text input files, see lesspipe(1)
#[ -x /usr/bin/lesspipe ] && eval "$(SHELL=/bin/sh lesspipe)"

# set variable identifying the chroot you work in (used in the prompt below)
#if [ -z "$debian_chroot" ] && [ -r /etc/debian_chroot ]; then
#debian_chroot=$(cat /etc/debian_chroot)
#fi

# set a fancy prompt (non-color, unless we know we "want" color)
#case "$TERM" in
#xterm-color) color_prompt=yes;;
#esac

# uncomment for a colored prompt, if the terminal has the capability; turned
# off by default to not distract the user: the focus in a terminal window
# should be on the output of commands, not on the prompt
force_color_prompt=yes

if [ -n "$force_color_prompt" ]; then
if [ -x /usr/bin/tput ] && tput setaf 1 >&/dev/null; then
# We have color support; assume it's compliant with Ecma-48
# (ISO/IEC-6429). (Lack of such support is extremely rare, and such
# a case would tend to support setf rather than setaf.)
color_prompt=yes
else
color_prompt=
fi
fi

# enable color support of ls and also add handy aliases
if [ -x /usr/bin/dircolors ]; then
test -r ~/.dircolors && eval "$(dircolors -b ~/.dircolors)" || eval "$(dircolors -b)"
alias ls='ls --color=auto'
fi

# alias ls='ls $LS_OPTIONS'
 alias ll='ls $LS_OPTIONS -lah'
# alias l='ls $LS_OPTIONS -lA'
#
# Some more alias to avoid making mistakes:
# alias rm='rm -i'
# alias cp='cp -i'
# alias mv='mv -i'

export TERM=xterm-256color

# FOLDERS
CMS_FOLDER_1='/cygdrive/c/wamp/www/cms'
CMS_FOLDER_2='/cygdrive/d/wamp/www/cms'
CMS_FOLDER_3='/cygdrive/e/Instal/wamp/www/cms/'
CMS_FOLDER="$( if [ -d "$CMS_FOLDER_1" ]; then echo "$CMS_FOLDER_1"; elif [ -d "$CMS_FOLDER_2" ]; then echo "$CMS_FOLDER_2"; elif [ -d "$CMS_FOLDER_3" ]; then echo "$CMS_FOLDER_3"; else echo "dir not found"; fi )"
alias cms='cd "$CMS_FOLDER"'

# GIT
alias gaa='git add -A'
alias gaas='gaa; gs'
alias gd='git diff'
alias gdc='_(){ git log $1^..$1 -p; }; _' # git diff commit [param HASH]
alias gdel='_(){ git branch -d $1 && git push origin :$1; }; _'
alias gc='git commit'
alias gcv='TAG=$( git show-ref --tags | tail -1 ); echo "Version history until" $( echo "$TAG" | cut -f3 -d"/" ); echo "---"; gv $( echo "$TAG" | cut -f1 -d" " )..'
alias gl='git log --decorate --all --oneline --graph'
alias gp='gpush'
alias gpush='git push --all; git push --tags'
alias gpull='git pull --all && git fetch -p'
alias guc='_(){ git reset --soft ${1:-HEAD~1}; }; _'
alias gs='git status'

# BASH
alias sshcmsadmin='ssh -i ~/.ssh/id_rsa cmsadmin@46.28.109.142'
alias less='less -rSX'
alias ll='ls -lah'
alias gtcs='cd "$CMS_FOLDER"/lib/locale/cs_CZ/LC_MESSAGES'
alias gtx='if [ -f messages.po ]; then find "$CMS_FOLDER" -iname "*.php" | xargs xgettext --from-code=UTF-8 -j; else echo "messages.po not found"; fi'
alias gtc='msgfmt messages.po'
alias phplint='find . -name "*.php" -type f -exec php -l "{}" \;'
