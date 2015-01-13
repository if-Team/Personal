<?php

/*
 * PocketMine Standard PHP Library
 * Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/PocketMine-SPL>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

class BaseClassLoader implements ClassLoader{

    /** @var \ClassLoader */
    private $parent;
    /** @var string[] */
    private $lookup = [];
    /** @var string[] */
    private $classes = [];


    /**
     * @param ClassLoader $parent
     */
    public function __construct(ClassLoader $parent = \null){
        $this->parent = $parent;
    }

    /**
     * Adds a path to the lookup list
     *
     * @param string $path
     * @param bool   $prepend
     */
    public function addPath($path, $prepend = \false){

        foreach($this->lookup as $p){
            if($p === $path){
                return;
            }
        }

        if($prepend){
            \array_unshift($this->lookup, $path);
        }else{
            $this->lookup[] = $path;
        }
    }

    /**
     * Removes a path from the lookup list
     *
     * @param $path
     */
    public function removePath($path){
        foreach($this->lookup as $i => $p){
            if($p === $path){
                unset($this->lookup[$i]);
            }
        }
    }

    /**
     * Returns an array of the classes loaded
     *
     * @return string[]
     */
    public function getClasses(){
        return $this->classes;
    }

    /**
     * Returns the parent ClassLoader, if any
     *
     * @return ClassLoader
     */
    public function getParent(){
        return $this->parent;
    }

    /**
     * Attaches the ClassLoader to the PHP runtime
     *
     * @param bool $prepend
     *
     * @return bool
     */
    public function register($prepend = \false){
        \spl_autoload_register([$this, "loadClass"], \true, $prepend);
    }

    /**
     * Called when there is a class to load
     *
     * @param string $name
     *
     * @return bool
     */
    public function loadClass($name){
        $path = $this->findClass($name);
        if($path !== \null){
            include($path);
            if(!\class_exists($name, \false) and !\interface_exists($name, \false) and !\trait_exists($name, \false)){
	            if($this->getParent() === \null){
		            throw new ClassNotFoundException("Class $name not found");
	            }
                return \false;
            }

	        if(\method_exists($name, "onClassLoaded") and (new ReflectionClass($name))->getMethod("onClassLoaded")->isStatic()){
		        $name::onClassLoaded();
	        }

            return \true;
        }elseif($this->getParent() === \null){
	        throw new ClassNotFoundException("Class $name not found");
        }

        return \false;
    }

    /**
     * Returns the path for the class, if any
     *
     * @param string $name
     *
     * @return string|null
     */
    public function findClass($name){
        $components = \explode("\\", $name);

        $fullName = \implode(DIRECTORY_SEPARATOR, $components) . ".php";

        foreach($this->lookup as $path){
            if(\file_exists($path . DIRECTORY_SEPARATOR . $fullName)){
                return $path . DIRECTORY_SEPARATOR . $fullName;
            }
        }

        return \null;
    }
}