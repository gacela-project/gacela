[Back to the index](../docs)

# Basic concepts about modules

First, what are the characteristics of a module?

1. It encapsulates code to implement a particular functionality.
2. It has an interface that lets clients access its functionality.
3. It is easily pluggable with another module that expects its interface.
4. It is usually packaged in a single unit so that it can be easily deployed.

## 1. Encapsulates a particular functionality

Think about the design of a module. How do you visualize it? Simple. It depends on its domain. The internal details of a
module design should depend only on the domain where it belongs, but...

- what about the common parts?
- how should a module interact with other modules?
- where are the instantiations of the domain services happening?
- how do I limit the boundaries between the infrastructure and the domain within my module?

## 2. Clients can access its functionality via an interface

The way a module will communicate with other modules will be ONLY via its Facade. That's it.

The Facade is the source of truth of what that module does. It belongs to the application layer, it's a mediator between
the infrastructure (Factory & Config) and your domain logic (everything else in your module).

## 3. Easily pluggable with another module

There must be an easy way to define the dependencies between your modules. The basic idea is to be able to easily access
the facades of other modules by exposing their FacadeInterface.

## 4. Usually packaged in a single unit

You should consider a module as an individual thing in order to decouple it from the rest of your modules. The logic in
charge of reading from the IO (Config) is the only class which is coupled somehow with the infrastructure, but the rest
should be decouple from the outside.

All the responsibilities that a module handles should remain close and together. The domain of that module should guide
its design.
