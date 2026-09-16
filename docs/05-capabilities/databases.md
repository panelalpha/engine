# Databases

Each project can have its own MySQL databases and database logins, on the engine's database server.

Ask your assistant rather than opening a MySQL shell as root. The engine tracks what it created, applies the project's naming rules, and counts it against the project's plan limits - none of which happens if you create things by hand.

## Creating a database

```text
Create a MySQL database named shop on this project, create a user for it,
and grant that user access.
```

Three things happen: the database is created, a login is created, and that login is given permission to use that database. **All three are needed.** A newly created database user has no access to anything until permissions are granted, which is the usual reason a fresh setup fails to connect.

## Two things that catch everyone out

**1. The names get a prefix.** Ask for `shop` and you get something like `myshop_shop`. Every project has its own prefix so two projects can both have a database called `shop` without colliding.

**Give your application the prefixed name the engine reports back**, not the short name you asked for. "Unknown database 'shop'" is almost always this.

**2. The database host is not `localhost`.** Your application runs in its own container; the database server runs elsewhere. `localhost` and `127.0.0.1` inside your application point at your application, where there is no database.

```text
Give me the MySQL connection details for this project.
```

Use the host and port that comes back, in your application's environment variables: [Environment variables](projects.md#environment-variables).

## phpMyAdmin

To browse a database in a web interface, ask for a sign-on link. A new install exposes that. If the assistant cannot give you one, those tools have been turned off: [Decide what the assistant may do](../04-connecting-your-ai/your-assistant.md#decide-what-the-assistant-may-do).

```text
Give me a phpMyAdmin sign-on link for this project.
```

You get a URL that logs you straight in. **It expires after five minutes.** Do not bookmark it - when it stops working, ask for a new one, which takes a second.

## Limits

Passwords must be at least 8 characters. Each project's plan caps how many databases it may have.

## When your application needs a database at deploy time

Some applications cannot start without a database and cannot ask for one interactively. For well-known applications the engine recognises, it creates one during the deploy automatically.

For a general repository, it will not guess. Create the database first, then give your application its details as environment variables: [Environment variables](projects.md#environment-variables).

## Troubleshooting

**"Unknown database" or "database does not exist".**
You gave the application the short name instead of the prefixed name the engine returned. See [above](#two-things-that-catch-everyone-out).

**"Cannot connect to MySQL server".**
Your application is pointed at `localhost`. Ask the assistant for the connection details and use the host it gives you.

**"Access denied for user".**
The user exists but has no permission on that database, or the password does not match. Ask the assistant to grant access, or reset the password.

**The application cannot log in after a rebuild, and used to work.**
The database still holds the password it was first created with, while your application now has a different one. Changing the environment variable does not change the password inside the database. See [Reading errors](../02-getting-started/reading-errors.md).

**"Database limit reached".**
The project has used up its plan's database allowance. Either raise the limit ([Limits](projects.md#limits)) or delete a database it no longer needs.

**phpMyAdmin gives a 404, or loops back to the login page.**
The sign-on link has expired - they last five minutes. Ask for a fresh one. If a new link does the same, the project's database user is missing; recreate it, then request the link again.

## From the server

Databases have no `pae` command of their own. Ask your assistant, or use phpMyAdmin.
