#!/usr/bin/env python3
"""Writes the two RGB ICC profiles the TIFF fixtures embed.

Built with Little CMS from published colorimetry, so the fixtures carry no
third-party profile: `python3 make-icc.py <dir>` writes qj-rgb-d65.icc and
qj-rgb-wide.icc there. Needs liblcms2.
"""
import ctypes
import ctypes.util
import sys

lib = ctypes.util.find_library('lcms2') or '/opt/homebrew/lib/liblcms2.dylib'
lcms = ctypes.CDLL(lib)


class XyY(ctypes.Structure):
    _fields_ = [('x', ctypes.c_double), ('y', ctypes.c_double), ('Y', ctypes.c_double)]


class Triple(ctypes.Structure):
    _fields_ = [('Red', XyY), ('Green', XyY), ('Blue', XyY)]


lcms.cmsBuildGamma.restype = ctypes.c_void_p
lcms.cmsBuildGamma.argtypes = [ctypes.c_void_p, ctypes.c_double]
lcms.cmsCreateRGBProfile.restype = ctypes.c_void_p
lcms.cmsCreateRGBProfile.argtypes = [ctypes.POINTER(XyY), ctypes.POINTER(Triple), ctypes.c_void_p * 3]
lcms.cmsMLUalloc.restype = ctypes.c_void_p
lcms.cmsMLUalloc.argtypes = [ctypes.c_void_p, ctypes.c_uint32]
lcms.cmsMLUsetASCII.argtypes = [ctypes.c_void_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p]
lcms.cmsWriteTag.argtypes = [ctypes.c_void_p, ctypes.c_uint32, ctypes.c_void_p]
lcms.cmsSetProfileVersion.argtypes = [ctypes.c_void_p, ctypes.c_double]
lcms.cmsSaveProfileToFile.argtypes = [ctypes.c_void_p, ctypes.c_char_p]
lcms.cmsCloseProfile.argtypes = [ctypes.c_void_p]

DESC = 0x64657363  # 'desc'
CPRT = 0x63707274  # 'cprt'


def text(value):
    mlu = lcms.cmsMLUalloc(None, 1)
    lcms.cmsMLUsetASCII(mlu, b'en', b'US', value.encode())
    return mlu


def profile(path, description, white, primaries, gamma):
    curve = lcms.cmsBuildGamma(None, gamma)
    curves = (ctypes.c_void_p * 3)(curve, curve, curve)
    handle = lcms.cmsCreateRGBProfile(ctypes.byref(XyY(*white, 1.0)), ctypes.byref(Triple(*(XyY(x, y, 1.0) for x, y in primaries))), curves)
    lcms.cmsSetProfileVersion(handle, 2.1)
    lcms.cmsWriteTag(handle, DESC, text(description))
    lcms.cmsWriteTag(handle, CPRT, text('No copyright, use freely'))
    lcms.cmsSaveProfileToFile(handle, path.encode())
    lcms.cmsCloseProfile(handle)


out = sys.argv[1] if len(sys.argv) > 1 else '.'
# Adobe RGB (1998) colorimetry: D65, gamma 2.2.
profile(f'{out}/qj-rgb-d65.icc', 'QJ Test RGB D65', (0.3127, 0.3290), [(0.64, 0.33), (0.21, 0.71), (0.15, 0.06)], 2.2)
# ROMM RGB colorimetry: D50, gamma 1.8.
profile(f'{out}/qj-rgb-wide.icc', 'QJ Test RGB Wide D50', (0.3457, 0.3585), [(0.7347, 0.2653), (0.1596, 0.8404), (0.0366, 0.0001)], 1.8)
