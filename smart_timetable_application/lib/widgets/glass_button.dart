import 'package:flutter/material.dart';
import 'dart:ui';

class GlassButton extends StatelessWidget {
  final Widget child;
  final VoidCallback? onPressed;
  final EdgeInsetsGeometry? padding;
  final EdgeInsetsGeometry? margin;
  final double? borderRadius;
  final Color? backgroundColor;
  final bool isSelected;
  final double? width;
  final double? height;

  const GlassButton({
    Key? key,
    required this.child,
    this.onPressed,
    this.padding,
    this.margin,
    this.borderRadius,
    this.backgroundColor,
    this.isSelected = false,
    this.width,
    this.height,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      width: width,
      height: height,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(borderRadius ?? 20),
        child: BackdropFilter(
          filter: ImageFilter.blur(
            sigmaX: 10,
            sigmaY: 10,
          ),
          child: Container(
            decoration: BoxDecoration(
              color: isSelected 
                  ? Colors.blue.withValues(alpha: 0.3)
                  : (backgroundColor ?? Colors.white).withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(borderRadius ?? 20),
              border: Border.all(
                color: isSelected 
                    ? Colors.blue.withValues(alpha: 0.5)
                    : Colors.white.withValues(alpha: 0.2),
                width: 1.5,
              ),
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: onPressed,
                borderRadius: BorderRadius.circular(borderRadius ?? 20),
                child: Container(
                  padding: padding ?? const EdgeInsets.all(16),
                  child: Center(child: child),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
