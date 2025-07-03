# CodingBlog - Blog PHP POO

This project is a blog application built in pure PHP using object-oriented programming and the MVC architecture pattern, without any external framework.
It is intended for learners, junior developers, or anyone who wants to understand the fundamentals of PHP web development by building a structured and maintainable application from scratch.

## Badges


## Features

- Article creation, editing, and deletion
- Commenting system
- Administration interface
- User login and registration
- Fully customized MVC architecture
- Clear separation of business code, views, and controllers


## Installation

1. Clone the repository

```bash
  git clone https://github.com/aerial978/coding-blog.git
  cd coding-blog
```

2. Install PHP dependencies

```bash
  composer install
```

3. Install the front-end dependencies (JavaScript/CSS)

```bash
  npm install
```

4. Configure your environment (.env file if used)
Create your MySQL database and update the configuration if necessary.

5. Generate the autoload

```bash
  composer dump-autoload
```


## Tech Stack

- **Server language**: PHP 8.x (OOP)
- **Database**: MySQL
- **Front-end**: HTML5, CSS3, JavaScript (Vanilla)
- **Quality tools**:
- PHPUnit (unit tests)
- PHPStan, PHPCS, PHP CS Fixer, PHPMD (static analysis, quality)
- ESLint (JavaScript)
- Stylelint (SCSS)
- Roave/Security-Advisories (dependency security)


## Running Tests & Quality

To run tests, run the following command

```bash
  composer test
```

To run the code analysis, run the following command

```bash
  composer lint        # PHPCS
  composer lint:fix    # PHPCBF
  composer cs:check    # PHP-CS-Fixer (dry run)
  composer cs:fix      # PHP-CS-Fixer (auto-fix)
  composer stan        # PHPStan
  composer md          # PHPMD
```

To run the Javascript & SCSS code analysis, run the following command

```bash
npm run lint:js      # ESLint
npm run lint:js:fix
npm run lint:scss    # Stylelint
```

## License

[MIT](https://choosealicense.com/licenses/mit/)