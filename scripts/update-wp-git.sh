#!/usr/bin/env bash

WP_PATH="/var/www/html/wp-content"
DOMAIN="gitlab.lovetirami.su"

# Обновленные репозитории
UPDATED=()

# Коменды для билда и т.д.
declare -A POST_COMMANDS

#POST_COMMANDS["discounts-for-woocommerce-subscriptions"]=""
#POST_COMMANDS["elasticpress"]=""
#POST_COMMANDS["medvise-admin-access"]=""
#POST_COMMANDS["medvise-custom-profile-page"]=""
#POST_COMMANDS["medvise-moneypot"]=""
POST_COMMANDS["medvise-otp-login"]="composer install"
#POST_COMMANDS["medvise-post-rating"]=""
#POST_COMMANDS["medvise-quiz-builder"]=""
#POST_COMMANDS["medvise-referrals"]=""
POST_COMMANDS["medvise-subscriptions"]="rm -rf node_modules && npm install && npm run build"
#POST_COMMANDS["medvise-user-modules"]=""
POST_COMMANDS["medvise-user-tour"]="rm -rf node_modules && npm install"
POST_COMMANDS["medvise-visualtree"]="composer install && rm -rf node_modules && npm install"
#POST_COMMANDS["robokassa"]=""
POST_COMMANDS["carenow"]="composer install && rm -rf node_modules && npm install && npm run prod && npm run build"

run_post_commands() {
  repo_dir="$1"
  repo_name=$(basename "$repo_dir")

  cmds="${POST_COMMANDS[$repo_name]}"

  if [ -n "$cmds" ]; then
    echo "Запускаем команды для: $repo_name"
    (cd "$repo_dir" && eval "$cmds")
  fi
}

update_repo() {
	DIR="$1"

	if [ -d "$DIR/.git" ]; then

		cd "$DIR" || return

    remote=$(git remote get-url origin 2>/dev/null)

		if [[ "$remote" == *"$DOMAIN"* ]]; then

		  # Если используется SSH — преобразовать в HTTPS
      if [[ "$remote" =~ ^git@([^:]+):(.+)\.git$ ]]; then
        host="${BASH_REMATCH[1]}"
        repo="${BASH_REMATCH[2]}"
        https_url="https://$host/$repo.git"

        echo "Переключение SSH -> HTTPS: $https_url"
        git remote set-url origin "$https_url"

        remote="$https_url"
      fi

			BEFORE=$(git rev-parse HEAD)

			git fetch --quiet
      branch=$(git rev-parse --abbrev-ref HEAD)

      git fetch --quiet
      git reset --hard origin/$branch --quiet
      git clean -df --quiet

			AFTER=$(git rev-parse HEAD)

			if [ "$BEFORE" != "$AFTER" ]; then
				UPDATED+=("$DIR")
				echo "ОБНОВЛЕНО: $DIR"
				run_post_commands "$DIR"
			else
				echo "Актуально: $DIR"
			fi
		else
			echo "Не наш репо: $dir"
		fi

		echo ""
	fi
}

for dir in "$WP_PATH/plugins"/*; do
	[ -d "$dir" ] && update_repo "$dir"
done

for dir in "$WP_PATH/themes"/*; do
	[ -d "$dir" ] && update_repo "$dir"
done

echo ""
echo "======================"
echo "Обновленные репозитории:"
echo "======================"

for repo in "${UPDATED[@]}"; do
	echo "$repo"
done